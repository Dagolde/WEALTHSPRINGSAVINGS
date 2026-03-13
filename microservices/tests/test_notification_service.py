"""
Unit Tests for Notification Service
Tests individual notification methods and error handling
"""

import pytest
from unittest.mock import AsyncMock, patch, MagicMock
import httpx
from app.services.notification.service import notification_dispatcher, EMAIL_TEMPLATES


class TestPushNotificationSending:
    """Test push notification sending functionality"""
    
    @pytest.mark.asyncio
    async def test_send_push_notification_success(self):
        """Test successful push notification sending"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock successful FCM response
            mock_response = MagicMock()
            mock_response.json.return_value = {"success": 1, "message_id": "fcm-123"}
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_push_notification(
                user_id=1,
                title="Test Notification",
                body="Test message",
                data={"key": "value"},
                fcm_token="test-fcm-token"
            )
            
            assert result is True
    
    @pytest.mark.asyncio
    async def test_send_push_notification_without_token(self):
        """Test push notification fails gracefully without FCM token"""
        result = await notification_dispatcher.send_push_notification(
            user_id=1,
            title="Test Notification",
            body="Test message",
            fcm_token=None
        )
        
        assert result is False
    
    @pytest.mark.asyncio
    async def test_send_push_notification_fcm_failure(self):
        """Test push notification handles FCM API failure"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock FCM failure response
            mock_response = MagicMock()
            mock_response.json.return_value = {"success": 0, "failure": 1}
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_push_notification(
                user_id=1,
                title="Test Notification",
                body="Test message",
                fcm_token="test-fcm-token"
            )
            
            assert result is False
    
    @pytest.mark.asyncio
    async def test_send_push_notification_http_error(self):
        """Test push notification handles HTTP errors with retry"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock HTTP error
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(
                side_effect=httpx.HTTPError("Connection failed")
            )
            mock_client.return_value = mock_context
            
            # The retry decorator will raise RetryError after max attempts
            # The service catches this in the outer exception handler
            with pytest.raises(Exception):  # Will raise RetryError
                await notification_dispatcher.send_push_notification(
                    user_id=1,
                    title="Test Notification",
                    body="Test message",
                    fcm_token="test-fcm-token"
                )


class TestSMSSending:
    """Test SMS sending functionality"""
    
    @pytest.mark.asyncio
    async def test_send_sms_success(self):
        """Test successful SMS sending"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock successful Termii response
            mock_response = MagicMock()
            mock_response.json.return_value = {"message_id": "sms-123", "status": "sent"}
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_sms(
                phone="+2348012345678",
                message="Test SMS message"
            )
            
            assert result is True
    
    @pytest.mark.asyncio
    async def test_send_sms_api_failure(self):
        """Test SMS sending handles API failure"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock Termii failure response (no message_id)
            mock_response = MagicMock()
            mock_response.json.return_value = {"error": "Invalid phone number"}
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_sms(
                phone="+2348012345678",
                message="Test SMS message"
            )
            
            assert result is False
    
    @pytest.mark.asyncio
    async def test_send_sms_http_error(self):
        """Test SMS sending handles HTTP errors with retry"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock HTTP error
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(
                side_effect=httpx.HTTPError("Connection timeout")
            )
            mock_client.return_value = mock_context
            
            # The retry decorator will raise RetryError after max attempts
            with pytest.raises(Exception):  # Will raise RetryError
                await notification_dispatcher.send_sms(
                    phone="+2348012345678",
                    message="Test SMS message"
                )


class TestEmailSending:
    """Test email sending functionality"""
    
    @pytest.mark.asyncio
    async def test_send_email_success(self):
        """Test successful email sending"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock successful SendGrid response (202 Accepted)
            mock_response = MagicMock()
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_email(
                email="test@example.com",
                subject="Test Email",
                template="default",
                data={"title": "Test", "message": "Test message"}
            )
            
            assert result is True
    
    @pytest.mark.asyncio
    async def test_send_email_with_contribution_reminder_template(self):
        """Test email sending with contribution reminder template"""
        with patch('httpx.AsyncClient') as mock_client:
            mock_response = MagicMock()
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_email(
                email="test@example.com",
                subject="Contribution Reminder",
                template="contribution_reminder",
                data={
                    "user_name": "John Doe",
                    "group_name": "Test Group",
                    "amount": "1000",
                    "due_date": "2024-01-15"
                }
            )
            
            assert result is True
    
    @pytest.mark.asyncio
    async def test_send_email_http_error(self):
        """Test email sending handles HTTP errors with retry"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock HTTP error
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(
                side_effect=httpx.HTTPError("SendGrid API error")
            )
            mock_client.return_value = mock_context
            
            # The retry decorator will raise RetryError after max attempts
            with pytest.raises(Exception):  # Will raise RetryError
                await notification_dispatcher.send_email(
                    email="test@example.com",
                    subject="Test Email",
                    template="default",
                    data={"message": "Test"}
                )


class TestMultiChannelNotification:
    """Test multi-channel notification dispatch"""
    
    @pytest.mark.asyncio
    async def test_send_multi_channel_all_channels_success(self):
        """Test multi-channel notification with all channels succeeding"""
        with patch('httpx.AsyncClient') as mock_client:
            # Mock successful responses for all channels
            mock_response = MagicMock()
            mock_response.json.return_value = {"success": 1, "message_id": "test-123"}
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            results = await notification_dispatcher.send_multi_channel(
                user_id=1,
                phone="+2348012345678",
                email="test@example.com",
                title="Test Notification",
                message="Test message",
                channels=['push', 'sms', 'email'],
                fcm_token="test-fcm-token"
            )
            
            assert len(results) == 3
            assert 'push' in results
            assert 'sms' in results
            assert 'email' in results
            assert all(results.values())  # All should be True
    
    @pytest.mark.asyncio
    async def test_send_multi_channel_partial_failure(self):
        """Test multi-channel notification with one channel failing"""
        with patch('httpx.AsyncClient') as mock_client:
            call_count = {'count': 0}
            
            async def mock_post(*args, **kwargs):
                call_count['count'] += 1
                mock_response = MagicMock()
                
                # First 3 calls (push with retries) fail, others succeed
                if call_count['count'] <= 3:
                    raise httpx.HTTPError("Push failed")
                else:
                    mock_response.json.return_value = {"success": 1, "message_id": "test-123"}
                    mock_response.raise_for_status = MagicMock()
                
                return mock_response
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = mock_post
            mock_client.return_value = mock_context
            
            results = await notification_dispatcher.send_multi_channel(
                user_id=1,
                phone="+2348012345678",
                email="test@example.com",
                title="Test Notification",
                message="Test message",
                channels=['push', 'sms', 'email'],
                fcm_token="test-fcm-token"
            )
            
            # All channels should have results
            assert len(results) == 3
            
            # Push should fail (caught by multi_channel), others should succeed
            assert results['push'] is False
            assert results['sms'] is True
            assert results['email'] is True
    
    @pytest.mark.asyncio
    async def test_send_multi_channel_selected_channels(self):
        """Test multi-channel notification with selected channels only"""
        with patch('httpx.AsyncClient') as mock_client:
            mock_response = MagicMock()
            mock_response.json.return_value = {"success": 1, "message_id": "test-123"}
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            # Only send to SMS and email
            results = await notification_dispatcher.send_multi_channel(
                user_id=1,
                phone="+2348012345678",
                email="test@example.com",
                title="Test Notification",
                message="Test message",
                channels=['sms', 'email']
            )
            
            assert len(results) == 2
            assert 'sms' in results
            assert 'email' in results
            assert 'push' not in results


class TestNotificationTemplateRendering:
    """Test email template rendering"""
    
    def test_render_default_template(self):
        """Test rendering default email template"""
        html = notification_dispatcher._render_email_template(
            'default',
            {'title': 'Test Title', 'message': 'Test Message'}
        )
        
        assert 'Test Title' in html
        assert 'Test Message' in html
        assert 'Rotational Contribution App' in html
    
    def test_render_contribution_reminder_template(self):
        """Test rendering contribution reminder template"""
        html = notification_dispatcher._render_email_template(
            'contribution_reminder',
            {
                'user_name': 'John Doe',
                'group_name': 'Test Group',
                'amount': '1000',
                'due_date': '2024-01-15'
            }
        )
        
        assert 'John Doe' in html
        assert 'Test Group' in html
        assert '1000' in html
        assert '2024-01-15' in html
        assert 'Contribution Reminder' in html
    
    def test_render_payout_notification_template(self):
        """Test rendering payout notification template"""
        html = notification_dispatcher._render_email_template(
            'payout_notification',
            {
                'user_name': 'Jane Smith',
                'group_name': 'Savings Group',
                'amount': '10000',
                'payout_date': '2024-01-20'
            }
        )
        
        assert 'Jane Smith' in html
        assert 'Savings Group' in html
        assert '10000' in html
        assert '2024-01-20' in html
        assert 'Payout Received' in html
    
    def test_render_missed_contribution_template(self):
        """Test rendering missed contribution alert template"""
        html = notification_dispatcher._render_email_template(
            'missed_contribution',
            {
                'user_name': 'Bob Johnson',
                'group_name': 'Daily Savers',
                'amount': '500',
                'missed_date': '2024-01-10'
            }
        )
        
        assert 'Bob Johnson' in html
        assert 'Daily Savers' in html
        assert '500' in html
        assert '2024-01-10' in html
        assert 'Missed Contribution' in html
    
    def test_render_group_invitation_template(self):
        """Test rendering group invitation template"""
        html = notification_dispatcher._render_email_template(
            'group_invitation',
            {
                'user_name': 'Alice Brown',
                'inviter_name': 'Charlie Davis',
                'group_name': 'Friends Savings',
                'amount': '2000',
                'total_members': '10',
                'cycle_days': '10',
                'group_code': 'ABC12345'
            }
        )
        
        assert 'Alice Brown' in html
        assert 'Charlie Davis' in html
        assert 'Friends Savings' in html
        assert '2000' in html
        assert 'ABC12345' in html
    
    def test_render_kyc_status_template(self):
        """Test rendering KYC status update template"""
        html = notification_dispatcher._render_email_template(
            'kyc_status',
            {
                'user_name': 'David Wilson',
                'status': 'verified'
            }
        )
        
        assert 'David Wilson' in html
        assert 'verified' in html
        assert 'KYC Status Update' in html
    
    def test_render_withdrawal_confirmation_template(self):
        """Test rendering withdrawal confirmation template"""
        html = notification_dispatcher._render_email_template(
            'withdrawal_confirmation',
            {
                'user_name': 'Emma Taylor',
                'amount': '5000',
                'account_number': '1234567890',
                'bank_name': 'Test Bank',
                'reference': 'WD-123456',
                'withdrawal_date': '2024-01-25'
            }
        )
        
        assert 'Emma Taylor' in html
        assert '5000' in html
        assert '1234567890' in html
        assert 'Test Bank' in html
        assert 'WD-123456' in html
    
    def test_render_unknown_template_uses_default(self):
        """Test that unknown template falls back to default"""
        html = notification_dispatcher._render_email_template(
            'unknown_template',
            {'title': 'Test', 'message': 'Message'}
        )
        
        # Should use default template
        assert 'Test' in html
        assert 'Message' in html


class TestErrorHandlingAndRetry:
    """Test error handling and retry logic"""
    
    @pytest.mark.asyncio
    async def test_retry_on_http_error(self):
        """Test that HTTP errors trigger retry logic"""
        with patch('httpx.AsyncClient') as mock_client:
            call_count = {'count': 0}
            
            async def mock_post(*args, **kwargs):
                call_count['count'] += 1
                if call_count['count'] < 3:
                    # Fail first 2 attempts
                    raise httpx.HTTPError("Temporary failure")
                else:
                    # Succeed on 3rd attempt
                    mock_response = MagicMock()
                    mock_response.json.return_value = {"success": 1}
                    mock_response.raise_for_status = MagicMock()
                    return mock_response
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = mock_post
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_push_notification(
                user_id=1,
                title="Test",
                body="Test",
                fcm_token="token"
            )
            
            # Should succeed after retries
            assert result is True
            assert call_count['count'] == 3
    
    @pytest.mark.asyncio
    async def test_max_retries_exceeded(self):
        """Test that max retries are respected"""
        with patch('httpx.AsyncClient') as mock_client:
            # Always fail
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(
                side_effect=httpx.HTTPError("Persistent failure")
            )
            mock_client.return_value = mock_context
            
            # Should raise RetryError after max retries
            with pytest.raises(Exception):  # Will raise RetryError
                await notification_dispatcher.send_sms(
                    phone="+2348012345678",
                    message="Test"
                )
    
    @pytest.mark.asyncio
    async def test_non_http_errors_dont_retry(self):
        """Test that non-HTTP errors are caught without retry"""
        with patch('httpx.AsyncClient') as mock_client:
            # Raise non-HTTP exception
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(
                side_effect=ValueError("Invalid data")
            )
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_email(
                email="test@example.com",
                subject="Test",
                template="default",
                data={}
            )
            
            # Should fail without retry
            assert result is False


class TestTemplateAvailability:
    """Test that all required templates are available"""
    
    def test_all_required_templates_exist(self):
        """Test that all required templates are defined"""
        required_templates = [
            'default',
            'contribution_reminder',
            'payout_notification',
            'missed_contribution',
            'group_invitation',
            'kyc_status',
            'withdrawal_confirmation'
        ]
        
        for template in required_templates:
            assert template in EMAIL_TEMPLATES, f"Template '{template}' is missing"
            assert len(EMAIL_TEMPLATES[template]) > 0, f"Template '{template}' is empty"
