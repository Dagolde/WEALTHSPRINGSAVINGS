"""
Scheduler Tasks
Celery tasks for scheduled operations
"""

from app.celery_app import celery_app
from app.config import settings
from app.services.notification.service import notification_dispatcher
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import AsyncSessionLocal
from datetime import date, datetime
import httpx
import logging
import asyncio

logger = logging.getLogger(__name__)


def run_async(coro):
    """Helper to run async functions in Celery tasks"""
    loop = asyncio.get_event_loop()
    return loop.run_until_complete(coro)


@celery_app.task(name='app.services.scheduler.tasks.process_daily_payouts')
def process_daily_payouts():
    """
    Process daily payouts for all active groups
    Runs daily at configured time (default: 00:00)
    
    Steps:
    1. Query all active groups with payouts due today
    2. Verify all contributions received for each group
    3. Call Laravel backend API to process eligible payouts
    4. Send notifications to recipients
    
    Validates Property 9 (Payout Eligibility)
    """
    logger.info("Starting daily payout processing")
    
    try:
        result = run_async(_process_daily_payouts_async())
        logger.info(f"Daily payout processing completed: {result}")
        return result
    
    except Exception as e:
        logger.error(f"Error processing daily payouts: {e}", exc_info=True)
        raise


async def _process_daily_payouts_async():
    """Async implementation of daily payout processing"""
    async with AsyncSessionLocal() as db:
        today = date.today()
        processed_count = 0
        failed_count = 0
        
        # Query active groups with payouts due today
        query = text("""
            SELECT DISTINCT g.id, g.name, g.contribution_amount, g.total_members, g.start_date
            FROM groups g
            INNER JOIN group_members gm ON g.id = gm.group_id
            WHERE g.status = 'active'
            AND g.start_date IS NOT NULL
            AND gm.payout_day = :day_number
            AND gm.has_received_payout = FALSE
            AND gm.status = 'active'
        """)
        
        # Calculate day number in cycle
        result = await db.execute(query, {"day_number": today.day})
        groups = result.fetchall()
        
        logger.info(f"Found {len(groups)} groups with payouts due today")
        
        for group in groups:
            group_id = group[0]
            group_name = group[1]
            contribution_amount = float(group[2])
            total_members = group[3]
            start_date = group[4]
            
            # Calculate current day in cycle
            days_since_start = (today - start_date).days + 1
            
            # Check if all contributions received for today
            contrib_query = text("""
                SELECT COUNT(*) as contribution_count
                FROM contributions
                WHERE group_id = :group_id
                AND contribution_date = :today
                AND payment_status = 'successful'
            """)
            
            contrib_result = await db.execute(
                contrib_query,
                {"group_id": group_id, "today": today}
            )
            contribution_count = contrib_result.scalar()
            
            if contribution_count < total_members:
                logger.warning(
                    f"Group {group_id} ({group_name}): Only {contribution_count}/{total_members} "
                    f"contributions received. Payout delayed."
                )
                continue
            
            # Get member who should receive payout today
            member_query = text("""
                SELECT gm.user_id, u.email, u.phone, u.name
                FROM group_members gm
                INNER JOIN users u ON gm.user_id = u.id
                WHERE gm.group_id = :group_id
                AND gm.payout_day = :day_number
                AND gm.has_received_payout = FALSE
                AND gm.status = 'active'
                LIMIT 1
            """)
            
            member_result = await db.execute(
                member_query,
                {"group_id": group_id, "day_number": days_since_start}
            )
            member = member_result.fetchone()
            
            if not member:
                logger.warning(f"No eligible member found for group {group_id} payout")
                continue
            
            user_id = member[0]
            user_email = member[1]
            user_phone = member[2]
            user_name = member[3]
            
            # Calculate payout amount
            payout_amount = contribution_amount * total_members
            
            # Call Laravel API to process payout
            try:
                async with httpx.AsyncClient(timeout=30.0) as client:
                    response = await client.post(
                        f"{settings.laravel_api_url}/payouts/process",
                        json={
                            "group_id": group_id,
                            "user_id": user_id,
                            "amount": payout_amount,
                            "payout_day": days_since_start,
                            "payout_date": today.isoformat(),
                        },
                        headers={
                            "Authorization": f"Bearer {settings.laravel_api_key}",
                            "Content-Type": "application/json",
                        }
                    )
                    
                    if response.status_code == 200 or response.status_code == 201:
                        processed_count += 1
                        logger.info(
                            f"Payout processed for user {user_id} in group {group_id}: "
                            f"₦{payout_amount}"
                        )
                        
                        # Send notification to recipient
                        await notification_dispatcher.send_multi_channel(
                            user_id=user_id,
                            phone=user_phone,
                            email=user_email,
                            title="Payout Received!",
                            message=f"You have received ₦{payout_amount:,.2f} from {group_name}. "
                                    f"The funds have been credited to your wallet.",
                            channels=['push', 'sms', 'email'],
                            data={
                                "type": "payout_received",
                                "group_id": group_id,
                                "amount": payout_amount
                            }
                        )
                    else:
                        failed_count += 1
                        logger.error(
                            f"Failed to process payout for group {group_id}: "
                            f"Status {response.status_code}, Response: {response.text}"
                        )
            
            except Exception as e:
                failed_count += 1
                logger.error(f"Error processing payout for group {group_id}: {e}", exc_info=True)
        
        return {
            "status": "success",
            "processed": processed_count,
            "failed": failed_count,
            "total_groups": len(groups)
        }


@celery_app.task(name='app.services.scheduler.tasks.send_contribution_reminders')
def send_contribution_reminders():
    """
    Send contribution reminders to users
    Runs daily at configured time (default: 09:00)
    
    Steps:
    1. Query all active groups
    2. Identify members who haven't contributed today
    3. Send reminder notifications via push, SMS, and email
    
    Validates Property 15 (Notification Delivery)
    """
    logger.info("Starting contribution reminder task")
    
    try:
        result = run_async(_send_contribution_reminders_async())
        logger.info(f"Contribution reminders sent: {result}")
        return result
    
    except Exception as e:
        logger.error(f"Error sending contribution reminders: {e}", exc_info=True)
        raise


async def _send_contribution_reminders_async():
    """Async implementation of contribution reminder sending"""
    async with AsyncSessionLocal() as db:
        today = date.today()
        reminder_count = 0
        
        # Query active group members who haven't contributed today
        query = text("""
            SELECT DISTINCT
                u.id as user_id,
                u.name,
                u.email,
                u.phone,
                g.id as group_id,
                g.name as group_name,
                g.contribution_amount
            FROM users u
            INNER JOIN group_members gm ON u.id = gm.user_id
            INNER JOIN groups g ON gm.group_id = g.id
            LEFT JOIN contributions c ON c.user_id = u.id 
                AND c.group_id = g.id 
                AND c.contribution_date = :today
                AND c.payment_status = 'successful'
            WHERE g.status = 'active'
            AND gm.status = 'active'
            AND u.status = 'active'
            AND c.id IS NULL
        """)
        
        result = await db.execute(query, {"today": today})
        members = result.fetchall()
        
        logger.info(f"Found {len(members)} members who need contribution reminders")
        
        for member in members:
            user_id = member[0]
            user_name = member[1]
            user_email = member[2]
            user_phone = member[3]
            group_id = member[4]
            group_name = member[5]
            contribution_amount = float(member[6])
            
            try:
                # Send multi-channel reminder
                await notification_dispatcher.send_multi_channel(
                    user_id=user_id,
                    phone=user_phone,
                    email=user_email,
                    title="Contribution Reminder",
                    message=f"Hi {user_name}, don't forget to make your daily contribution of "
                            f"₦{contribution_amount:,.2f} to {group_name} today!",
                    channels=['push', 'sms'],
                    data={
                        "type": "contribution_reminder",
                        "group_id": group_id,
                        "amount": contribution_amount
                    }
                )
                
                reminder_count += 1
                logger.info(f"Reminder sent to user {user_id} for group {group_id}")
            
            except Exception as e:
                logger.error(f"Error sending reminder to user {user_id}: {e}")
        
        return {
            "status": "success",
            "reminders_sent": reminder_count,
            "total_members": len(members)
        }


@celery_app.task(name='app.services.scheduler.tasks.detect_missed_contributions')
def detect_missed_contributions():
    """
    Detect and alert on missed contributions
    Runs daily at end of day (default: 23:00)
    
    Steps:
    1. Query all active groups
    2. Identify members who missed their contribution today
    3. Send missed contribution alerts
    4. Log missed contributions for reporting
    """
    logger.info("Starting missed contribution detection")
    
    try:
        result = run_async(_detect_missed_contributions_async())
        logger.info(f"Missed contribution detection completed: {result}")
        return result
    
    except Exception as e:
        logger.error(f"Error detecting missed contributions: {e}", exc_info=True)
        raise


async def _detect_missed_contributions_async():
    """Async implementation of missed contribution detection"""
    async with AsyncSessionLocal() as db:
        today = date.today()
        missed_count = 0
        
        # Query active group members who missed contribution today
        query = text("""
            SELECT DISTINCT
                u.id as user_id,
                u.name,
                u.email,
                u.phone,
                g.id as group_id,
                g.name as group_name,
                g.contribution_amount
            FROM users u
            INNER JOIN group_members gm ON u.id = gm.user_id
            INNER JOIN groups g ON gm.group_id = g.id
            LEFT JOIN contributions c ON c.user_id = u.id 
                AND c.group_id = g.id 
                AND c.contribution_date = :today
                AND c.payment_status = 'successful'
            WHERE g.status = 'active'
            AND gm.status = 'active'
            AND u.status = 'active'
            AND c.id IS NULL
        """)
        
        result = await db.execute(query, {"today": today})
        members = result.fetchall()
        
        logger.info(f"Found {len(members)} members who missed contributions today")
        
        for member in members:
            user_id = member[0]
            user_name = member[1]
            user_email = member[2]
            user_phone = member[3]
            group_id = member[4]
            group_name = member[5]
            contribution_amount = float(member[6])
            
            try:
                # Send missed contribution alert
                await notification_dispatcher.send_multi_channel(
                    user_id=user_id,
                    phone=user_phone,
                    email=user_email,
                    title="Missed Contribution Alert",
                    message=f"You missed your contribution of ₦{contribution_amount:,.2f} "
                            f"to {group_name} today. Please contribute as soon as possible "
                            f"to avoid affecting group payouts.",
                    channels=['push', 'sms', 'email'],
                    data={
                        "type": "missed_contribution",
                        "group_id": group_id,
                        "amount": contribution_amount,
                        "date": today.isoformat()
                    }
                )
                
                # Log missed contribution
                log_query = text("""
                    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, created_at)
                    VALUES (:user_id, 'missed_contribution', 'contribution', :group_id, :data, :created_at)
                """)
                
                await db.execute(
                    log_query,
                    {
                        "user_id": user_id,
                        "group_id": group_id,
                        "data": f'{{"group_id": {group_id}, "date": "{today.isoformat()}", "amount": {contribution_amount}}}',
                        "created_at": datetime.now()
                    }
                )
                
                missed_count += 1
                logger.info(f"Missed contribution logged for user {user_id} in group {group_id}")
            
            except Exception as e:
                logger.error(f"Error processing missed contribution for user {user_id}: {e}")
        
        await db.commit()
        
        return {
            "status": "success",
            "missed_contributions": missed_count,
            "total_members": len(members)
        }


@celery_app.task(name='app.services.scheduler.tasks.check_group_completion')
def check_group_completion():
    """
    Check for completed group cycles
    Runs daily (default: 01:00)
    
    Steps:
    1. Query all active groups
    2. Check if all members have received payouts
    3. Update group status to 'completed'
    4. Send completion notifications to all members
    
    Validates Property 18 (Group Cycle Completion Invariant)
    """
    logger.info("Starting group completion check")
    
    try:
        result = run_async(_check_group_completion_async())
        logger.info(f"Group completion check completed: {result}")
        return result
    
    except Exception as e:
        logger.error(f"Error checking group completion: {e}", exc_info=True)
        raise


async def _check_group_completion_async():
    """Async implementation of group completion checking"""
    async with AsyncSessionLocal() as db:
        completed_count = 0
        
        # Query active groups where all members have received payouts
        query = text("""
            SELECT 
                g.id,
                g.name,
                g.total_members,
                COUNT(gm.id) as total_group_members,
                SUM(CASE WHEN gm.has_received_payout = TRUE THEN 1 ELSE 0 END) as members_paid
            FROM groups g
            INNER JOIN group_members gm ON g.id = gm.group_id
            WHERE g.status = 'active'
            AND gm.status = 'active'
            GROUP BY g.id, g.name, g.total_members
            HAVING COUNT(gm.id) = g.total_members
            AND SUM(CASE WHEN gm.has_received_payout = TRUE THEN 1 ELSE 0 END) = g.total_members
        """)
        
        result = await db.execute(query)
        groups = result.fetchall()
        
        logger.info(f"Found {len(groups)} groups ready for completion")
        
        for group in groups:
            group_id = group[0]
            group_name = group[1]
            total_members = group[2]
            
            try:
                # Update group status to completed
                update_query = text("""
                    UPDATE groups
                    SET status = 'completed', updated_at = :updated_at
                    WHERE id = :group_id
                """)
                
                await db.execute(
                    update_query,
                    {"group_id": group_id, "updated_at": datetime.now()}
                )
                
                # Get all group members for notification
                members_query = text("""
                    SELECT u.id, u.name, u.email, u.phone
                    FROM users u
                    INNER JOIN group_members gm ON u.id = gm.user_id
                    WHERE gm.group_id = :group_id
                    AND gm.status = 'active'
                """)
                
                members_result = await db.execute(members_query, {"group_id": group_id})
                members = members_result.fetchall()
                
                # Send completion notifications to all members
                for member in members:
                    user_id = member[0]
                    user_name = member[1]
                    user_email = member[2]
                    user_phone = member[3]
                    
                    try:
                        await notification_dispatcher.send_multi_channel(
                            user_id=user_id,
                            phone=user_phone,
                            email=user_email,
                            title="Group Cycle Completed!",
                            message=f"Congratulations! The {group_name} group cycle has been "
                                    f"completed successfully. All {total_members} members have "
                                    f"received their payouts. Thank you for participating!",
                            channels=['push', 'sms', 'email'],
                            data={
                                "type": "group_completed",
                                "group_id": group_id,
                                "group_name": group_name
                            }
                        )
                    except Exception as e:
                        logger.error(f"Error sending completion notification to user {user_id}: {e}")
                
                completed_count += 1
                logger.info(f"Group {group_id} ({group_name}) marked as completed")
            
            except Exception as e:
                logger.error(f"Error completing group {group_id}: {e}")
        
        await db.commit()
        
        return {
            "status": "success",
            "completed_groups": completed_count,
            "total_groups": len(groups)
        }

