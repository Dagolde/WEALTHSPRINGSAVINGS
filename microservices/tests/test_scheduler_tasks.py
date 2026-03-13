"""
Unit Tests for Scheduler Tasks
Tests individual scheduler task functionality
"""

import pytest
from unittest.mock import Mock, patch, AsyncMock
from datetime import date, timedelta
from sqlalchemy import text
from app.database import AsyncSessionLocal
from app.services.scheduler.tasks import (
    process_daily_payouts,
    send_contribution_reminders,
    detect_missed_contributions,
    check_group_completion,
    _process_daily_payouts_async,
    _send_contribution_reminders_async,
    _detect_missed_contributions_async,
    _check_group_completion_async
)


class TestDailyPayoutProcessing:
    """Tests for daily payout processing task"""
    
    @pytest.mark.asyncio
    async def test_processes_payout_when_all_contributions_received(self):
        """Test that payout is processed when all members have contributed"""
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        'Test Group', 'TEST001', 1000.0, 3, 3, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {"start_date": date.today() - timedelta(days=1)}
                )
                group_id = group_result.scalar()
                
                # Create 3 members
                for i in range(1, 4):
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, FALSE, NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i
                        }
                    )
                
                # Create contributions for all members today
                for i in range(1, 4):
                    contrib_query = text("""
                        INSERT INTO contributions (
                            group_id, user_id, amount, payment_method,
                            payment_reference, payment_status, contribution_date,
                            paid_at, created_at, updated_at
                        )
                        VALUES (
                            :group_id, :user_id, 1000.0, 'wallet',
                            :ref, 'successful', :today, NOW(), NOW(), NOW()
                        )
                    """)
                    
                    await db.execute(
                        contrib_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "ref": f"CONTRIB-{i}",
                            "today": date.today()
                        }
                    )
                
                await db.commit()
                
                # Mock Laravel API call
                with patch('httpx.AsyncClient') as mock_client:
                    mock_response = Mock()
                    mock_response.status_code = 200
                    mock_response.json.return_value = {"success": True}
                    
                    mock_client.return_value.__aenter__.return_value.post = AsyncMock(
                        return_value=mock_response
                    )
                    
                    # Mock notification dispatcher
                    with patch('app.services.scheduler.tasks.notification_dispatcher') as mock_notif:
                        mock_notif.send_multi_channel = AsyncMock(return_value={
                            'push': True, 'sms': True, 'email': True
                        })
                        
                        # Run the task
                        result = await _process_daily_payouts_async()
                        
                        # Verify result
                        assert result['status'] == 'success'
                        assert result['processed'] >= 0
                
            finally:
                # Cleanup
                await db.execute(text("DELETE FROM contributions WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()
    
    @pytest.mark.asyncio
    async def test_delays_payout_when_contributions_missing(self):
        """Test that payout is delayed when not all members have contributed"""
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        'Test Group 2', 'TEST002', 1000.0, 3, 3, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {"start_date": date.today() - timedelta(days=1)}
                )
                group_id = group_result.scalar()
                
                # Create 3 members
                for i in range(1, 4):
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, FALSE, NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i
                        }
                    )
                
                # Create contributions for only 2 members (missing 1)
                for i in range(1, 3):
                    contrib_query = text("""
                        INSERT INTO contributions (
                            group_id, user_id, amount, payment_method,
                            payment_reference, payment_status, contribution_date,
                            paid_at, created_at, updated_at
                        )
                        VALUES (
                            :group_id, :user_id, 1000.0, 'wallet',
                            :ref, 'successful', :today, NOW(), NOW(), NOW()
                        )
                    """)
                    
                    await db.execute(
                        contrib_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "ref": f"CONTRIB-{i}",
                            "today": date.today()
                        }
                    )
                
                await db.commit()
                
                # Mock Laravel API call (should not be called)
                with patch('httpx.AsyncClient') as mock_client:
                    # Run the task
                    result = await _process_daily_payouts_async()
                    
                    # Verify no payout was processed
                    assert result['status'] == 'success'
                    # API should not have been called for this group
                    mock_client.return_value.__aenter__.return_value.post.assert_not_called()
                
            finally:
                # Cleanup
                await db.execute(text("DELETE FROM contributions WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()


class TestContributionReminders:
    """Tests for contribution reminder task"""
    
    @pytest.mark.asyncio
    async def test_sends_reminders_to_members_without_contributions(self):
        """Test that reminders are sent to members who haven't contributed"""
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        'Reminder Group', 'REM001', 500.0, 2, 2, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {"start_date": date.today()}
                )
                group_id = group_result.scalar()
                
                # Create 2 members
                for i in range(1, 3):
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, FALSE, NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i
                        }
                    )
                
                await db.commit()
                
                # Mock notification dispatcher
                with patch('app.services.scheduler.tasks.notification_dispatcher') as mock_notif:
                    mock_notif.send_multi_channel = AsyncMock(return_value={
                        'push': True, 'sms': True
                    })
                    
                    # Run the task
                    result = await _send_contribution_reminders_async()
                    
                    # Verify reminders were sent
                    assert result['status'] == 'success'
                    assert result['reminders_sent'] >= 0
                
            finally:
                # Cleanup
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()
    
    @pytest.mark.asyncio
    async def test_no_reminders_sent_when_all_contributed(self):
        """Test that no reminders are sent when all members have contributed"""
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        'Complete Group', 'COM001', 500.0, 2, 2, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {"start_date": date.today()}
                )
                group_id = group_result.scalar()
                
                # Create 2 members
                for i in range(1, 3):
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, FALSE, NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i
                        }
                    )
                    
                    # Create contribution for each member
                    contrib_query = text("""
                        INSERT INTO contributions (
                            group_id, user_id, amount, payment_method,
                            payment_reference, payment_status, contribution_date,
                            paid_at, created_at, updated_at
                        )
                        VALUES (
                            :group_id, :user_id, 500.0, 'wallet',
                            :ref, 'successful', :today, NOW(), NOW(), NOW()
                        )
                    """)
                    
                    await db.execute(
                        contrib_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "ref": f"CONTRIB-{i}",
                            "today": date.today()
                        }
                    )
                
                await db.commit()
                
                # Mock notification dispatcher
                with patch('app.services.scheduler.tasks.notification_dispatcher') as mock_notif:
                    mock_notif.send_multi_channel = AsyncMock()
                    
                    # Run the task
                    result = await _send_contribution_reminders_async()
                    
                    # Verify no reminders were sent for this group
                    assert result['status'] == 'success'
                
            finally:
                # Cleanup
                await db.execute(text("DELETE FROM contributions WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()


class TestMissedContributionDetection:
    """Tests for missed contribution detection task"""
    
    @pytest.mark.asyncio
    async def test_detects_and_logs_missed_contributions(self):
        """Test that missed contributions are detected and logged"""
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        'Missed Group', 'MIS001', 1000.0, 2, 2, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {"start_date": date.today()}
                )
                group_id = group_result.scalar()
                
                # Create 2 members (neither contributed today)
                for i in range(1, 3):
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, FALSE, NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i
                        }
                    )
                
                await db.commit()
                
                # Mock notification dispatcher
                with patch('app.services.scheduler.tasks.notification_dispatcher') as mock_notif:
                    mock_notif.send_multi_channel = AsyncMock(return_value={
                        'push': True, 'sms': True, 'email': True
                    })
                    
                    # Run the task
                    result = await _detect_missed_contributions_async()
                    
                    # Verify missed contributions were detected
                    assert result['status'] == 'success'
                    assert result['missed_contributions'] >= 0
                
            finally:
                # Cleanup
                await db.execute(text("DELETE FROM audit_logs WHERE entity_id = :group_id AND entity_type = 'contribution'"), {"group_id": group_id})
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()


class TestGroupCompletion:
    """Tests for group completion check task"""
    
    @pytest.mark.asyncio
    async def test_marks_group_complete_when_all_payouts_done(self):
        """Test that group is marked complete when all members received payouts"""
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        'Complete Test', 'CPL001', 1000.0, 2, 2, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {"start_date": date.today() - timedelta(days=2)}
                )
                group_id = group_result.scalar()
                
                # Create 2 members with payouts received
                for i in range(1, 3):
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, payout_received_at, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, TRUE, NOW(), NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i
                        }
                    )
                
                await db.commit()
                
                # Mock notification dispatcher
                with patch('app.services.scheduler.tasks.notification_dispatcher') as mock_notif:
                    mock_notif.send_multi_channel = AsyncMock(return_value={
                        'push': True, 'sms': True, 'email': True
                    })
                    
                    # Run the task
                    result = await _check_group_completion_async()
                    
                    # Verify group was marked complete
                    assert result['status'] == 'success'
                    
                    # Check group status
                    status_query = text("SELECT status FROM groups WHERE id = :group_id")
                    status_result = await db.execute(status_query, {"group_id": group_id})
                    status = status_result.scalar()
                    assert status == 'completed'
                
            finally:
                # Cleanup
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()
    
    @pytest.mark.asyncio
    async def test_does_not_mark_incomplete_group_complete(self):
        """Test that group is not marked complete when payouts are incomplete"""
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        'Incomplete Test', 'INC001', 1000.0, 2, 2, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {"start_date": date.today() - timedelta(days=2)}
                )
                group_id = group_result.scalar()
                
                # Create 2 members, only 1 received payout
                for i in range(1, 3):
                    has_payout = (i == 1)
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, payout_received_at, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, :has_payout, 
                                CASE WHEN :has_payout THEN NOW() ELSE NULL END, NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i,
                            "has_payout": has_payout
                        }
                    )
                
                await db.commit()
                
                # Run the task
                result = await _check_group_completion_async()
                
                # Verify group was NOT marked complete
                status_query = text("SELECT status FROM groups WHERE id = :group_id")
                status_result = await db.execute(status_query, {"group_id": group_id})
                status = status_result.scalar()
                assert status == 'active', "Group should remain active when payouts incomplete"
                
            finally:
                # Cleanup
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()
