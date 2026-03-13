"""
Notification Service Implementation
Multi-channel notification dispatcher with FCM, SMS, and Email support
"""

from typing import Dict, List, Any, Optional
import httpx
from app.config import settings
import logging
import json
from tenacity import retry, stop_after_attempt, wait_exponential, retry_if_exception_type

logger = logging.getLogger(__name__)


# Email Templates
EMAIL_TEMPLATES = {
    'default': """
        <html>
            <body style="font-family: Arial, sans-serif; padding: 20px;">
                <h2>{{title}}</h2>
                <p>{{message}}</p>
                <hr>
                <p style="color: #666; font-size: 12px;">
                    Rotational Contribution App<br>
                    This is an automated message, please do not reply.
                </p>
            </body>
        </html>
    """,
    
    'contribution_reminder': """
        <html>
            <body style="font-family: Arial, sans-serif; padding: 20px;">
                <h2>Contribution Reminder</h2>
                <p>Hello {{user_name}},</p>
                <p>This is a reminder to make your daily contribution for <strong>{{group_name}}</strong>.</p>
                <p><strong>Amount:</strong> ₦{{amount}}</p>
                <p><strong>Due Date:</strong> {{due_date}}</p>
                <p>Please ensure you contribute before the deadline to avoid penalties.</p>
                <hr>
                <p style="color: #666; font-size: 12px;">
                    Rotational Contribution App<br>
                    This is an automated message, please do not reply.
                </p>
            </body>
        </html>
    """,
    
    'payout_notification': """
        <html>
            <body style="font-family: Arial, sans-serif; padding: 20px;">
                <h2>🎉 Payout Received!</h2>
                <p>Hello {{user_name}},</p>
                <p>Great news! Your payout has been processed successfully.</p>
                <p><strong>Group:</strong> {{group_name}}</p>
                <p><strong>Amount:</strong> ₦{{amount}}</p>
                <p><strong>Date:</strong> {{payout_date}}</p>
                <p>The funds have been credited to your wallet.</p>
                <hr>
                <p style="color: #666; font-size: 12px;">
                    Rotational Contribution App<br>
                    This is an automated message, please do not reply.
                </p>
            </body>
        </html>
    """,
    
    'missed_contribution': """
        <html>
            <body style="font-family: Arial, sans-serif; padding: 20px;">
                <h2>⚠️ Missed Contribution Alert</h2>
                <p>Hello {{user_name}},</p>
                <p>You missed your contribution for <strong>{{group_name}}</strong> on {{missed_date}}.</p>
                <p><strong>Amount:</strong> ₦{{amount}}</p>
                <p>Please make your contribution as soon as possible to avoid affecting the group's payout schedule.</p>
                <hr>
                <p style="color: #666; font-size: 12px;">
                    Rotational Contribution App<br>
                    This is an automated message, please do not reply.
                </p>
            </body>
        </html>
    """,
    
    'group_invitation': """
        <html>
            <body style="font-family: Arial, sans-serif; padding: 20px;">
                <h2>Group Invitation</h2>
                <p>Hello {{user_name}},</p>
                <p><strong>{{inviter_name}}</strong> has invited you to join <strong>{{group_name}}</strong>.</p>
                <p><strong>Contribution Amount:</strong> ₦{{amount}}</p>
                <p><strong>Total Members:</strong> {{total_members}}</p>
                <p><strong>Cycle Duration:</strong> {{cycle_days}} days</p>
                <p><strong>Group Code:</strong> {{group_code}}</p>
                <p>Use the group code to join via the mobile app.</p>
                <hr>
                <p style="color: #666; font-size: 12px;">
                    Rotational Contribution App<br>
                    This is an automated message, please do not reply.
                </p>
            </body>
        </html>
    """,
    
    'kyc_status': """
        <html>
            <body style="font-family: Arial, sans-serif; padding: 20px;">
                <h2>KYC Status Update</h2>
                <p>Hello {{user_name}},</p>
                <p>Your KYC verification status has been updated to: <strong>{{status}}</strong></p>
                {{#if reason}}
                <p><strong>Reason:</strong> {{reason}}</p>
                {{/if}}
                {{#if status_verified}}
                <p>You can now participate in contribution groups and make transactions.</p>
                {{/if}}
                <hr>
                <p style="color: #666; font-size: 12px;">
                    Rotational Contribution App<br>
                    This is an automated message, please do not reply.
                </p>
            </body>
        </html>
    """,
    
    'withdrawal_confirmation': """
        <html>
            <body style="font-family: Arial, sans-serif; padding: 20px;">
                <h2>Withdrawal Confirmation</h2>
                <p>Hello {{user_name}},</p>
                <p>Your withdrawal request has been processed successfully.</p>
                <p><strong>Amount:</strong> ₦{{amount}}</p>
                <p><strong>Bank Account:</strong> {{account_number}} ({{bank_name}})</p>
                <p><strong>Reference:</strong> {{reference}}</p>
                <p><strong>Date:</strong> {{withdrawal_date}}</p>
                <p>The funds should arrive in your bank account within 24 hours.</p>
                <hr>
                <p style="color: #666; font-size: 12px;">
                    Rotational Contribution App<br>
                    This is an automated message, please do not reply.
                </p>
            </body>
        </html>
    """
}


class NotificationDispatcher:
    """Multi-channel notification service with error handling and retry logic"""
    
    def __init__(self):
        """Initialize notification dispatcher"""
        self.fcm_url = "https://fcm.googleapis.com/fcm/send"
        self.max_retries = 3
    
    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=1, min=2, max=10),
        retry=retry_if_exception_type(httpx.HTTPError)
    )
    async def send_push_notification(
        self,
        user_id: int,
        title: str,
        body: str,
        data: Dict[str, Any] = None,
        fcm_token: Optional[str] = None
    ) -> bool:
        """
        Send push notification via Firebase Cloud Messaging
        
        Args:
            user_id: User ID
            title: Notification title
            body: Notification body
            data: Additional data payload
            fcm_token: FCM device token (if not provided, will be fetched from DB)
        
        Returns:
            True if successful, False otherwise
        """
        try:
            # For now, we'll accept FCM token as parameter
            # In production, this would be fetched from user's device tokens in DB
            if not fcm_token:
                logger.warning(f"No FCM token provided for user {user_id}")
                return False
            
            headers = {
                "Authorization": f"Bearer {settings.fcm_server_key}",
                "Content-Type": "application/json"
            }
            
            payload = {
                "to": fcm_token,
                "notification": {
                    "title": title,
                    "body": body,
                    "sound": "default"
                },
                "data": data or {},
                "priority": "high"
            }
            
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(
                    self.fcm_url,
                    headers=headers,
                    json=payload
                )
                response.raise_for_status()
                
                result = response.json()
                
                if result.get("success", 0) > 0:
                    logger.info(f"Push notification sent to user {user_id}: {title}")
                    return True
                else:
                    logger.error(f"FCM push failed: {result}")
                    return False
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error sending push notification: {e}")
            raise  # Let retry decorator handle it
        except Exception as e:
            logger.error(f"Error sending push notification: {e}")
            return False
    
    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=1, min=2, max=10),
        retry=retry_if_exception_type(httpx.HTTPError)
    )
    async def send_sms(self, phone: str, message: str) -> bool:
        """
        Send SMS via Termii
        
        Args:
            phone: Phone number (international format)
            message: SMS message content
        
        Returns:
            True if successful, False otherwise
        """
        try:
            url = f"{settings.termii_base_url}/sms/send"
            
            payload = {
                "to": phone,
                "from": settings.termii_sender_id,
                "sms": message,
                "type": "plain",
                "channel": "generic",
                "api_key": settings.termii_api_key
            }
            
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(url, json=payload)
                response.raise_for_status()
                
                data = response.json()
                
                if data.get("message_id"):
                    logger.info(f"SMS sent to {phone}")
                    return True
                else:
                    logger.error(f"SMS sending failed: {data}")
                    return False
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error sending SMS: {e}")
            raise  # Let retry decorator handle it
        except Exception as e:
            logger.error(f"Error sending SMS: {e}")
            return False
    
    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=1, min=2, max=10),
        retry=retry_if_exception_type(httpx.HTTPError)
    )
    async def send_email(
        self,
        email: str,
        subject: str,
        template: str,
        data: Dict[str, Any] = None
    ) -> bool:
        """
        Send email via SendGrid
        
        Args:
            email: Recipient email address
            subject: Email subject
            template: Email template name
            data: Template data
        
        Returns:
            True if successful, False otherwise
        """
        try:
            url = "https://api.sendgrid.com/v3/mail/send"
            
            headers = {
                "Authorization": f"Bearer {settings.sendgrid_api_key}",
                "Content-Type": "application/json"
            }
            
            # Render email content from template
            html_content = self._render_email_template(template, data or {})
            
            payload = {
                "personalizations": [
                    {
                        "to": [{"email": email}],
                        "subject": subject
                    }
                ],
                "from": {
                    "email": settings.sendgrid_from_email,
                    "name": settings.sendgrid_from_name
                },
                "content": [
                    {
                        "type": "text/html",
                        "value": html_content
                    }
                ]
            }
            
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(url, headers=headers, json=payload)
                response.raise_for_status()
                
                logger.info(f"Email sent to {email}: {subject}")
                return True
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error sending email: {e}")
            raise  # Let retry decorator handle it
        except Exception as e:
            logger.error(f"Error sending email: {e}")
            return False
    
    def _render_email_template(self, template: str, data: Dict[str, Any]) -> str:
        """
        Render email template with data
        
        Args:
            template: Template name
            data: Template data
        
        Returns:
            Rendered HTML content
        """
        # Get template content
        template_content = EMAIL_TEMPLATES.get(template, EMAIL_TEMPLATES['default'])
        
        # Simple template rendering (replace placeholders)
        html = template_content
        for key, value in data.items():
            html = html.replace(f"{{{{{key}}}}}", str(value))
        
        return html
    
    async def send_multi_channel(
        self,
        user_id: int,
        phone: str,
        email: str,
        title: str,
        message: str,
        channels: List[str] = None,
        data: Dict[str, Any] = None,
        fcm_token: Optional[str] = None
    ) -> Dict[str, bool]:
        """
        Send notification across multiple channels with error handling
        
        Args:
            user_id: User ID
            phone: Phone number
            email: Email address
            title: Notification title
            message: Notification message
            channels: List of channels to use ['push', 'sms', 'email']
            data: Additional data
            fcm_token: FCM device token for push notifications
        
        Returns:
            Dict with status for each channel
        """
        if channels is None:
            channels = ['push', 'sms', 'email']
        
        results = {}
        errors = {}
        
        # Send to each channel independently (failures don't affect other channels)
        if 'push' in channels:
            try:
                results['push'] = await self.send_push_notification(
                    user_id, title, message, data, fcm_token
                )
            except Exception as e:
                results['push'] = False
                errors['push'] = str(e)
                logger.error(f"Push notification failed for user {user_id}: {e}")
        
        if 'sms' in channels:
            try:
                results['sms'] = await self.send_sms(phone, message)
            except Exception as e:
                results['sms'] = False
                errors['sms'] = str(e)
                logger.error(f"SMS failed for {phone}: {e}")
        
        if 'email' in channels:
            try:
                template = data.get('template', 'default') if data else 'default'
                results['email'] = await self.send_email(
                    email, title, template, {'message': message, **(data or {})}
                )
            except Exception as e:
                results['email'] = False
                errors['email'] = str(e)
                logger.error(f"Email failed for {email}: {e}")
        
        logger.info(f"Multi-channel notification sent to user {user_id}: {results}")
        
        if errors:
            logger.warning(f"Some channels failed: {errors}")
        
        return results


# Global notification dispatcher instance
notification_dispatcher = NotificationDispatcher()
