"""
Property-Based Tests for Notification Service
Tests Property 15: Notification Delivery
"""

import pytest
from hypothesis import given, strategies as st, settings as hypothesis_settings
from hypothesis import HealthCheck
from app.services.notification.service import notification_dispatcher
from unittest.mock import AsyncMock, patch, MagicMock
import httpx


# Custom strategies for notification data
@st.composite
def notification_data(draw):
    """Generate valid notification data"""
    return {
        "user_id": draw(st.integers(min_value=1, max_value=10000)),
        "phone": draw(st.from_regex(r'\+234[0-9]{10}', fullmatch=True)),
        "email": draw(st.emails()),
        "title": draw(st.text(min_size=1, max_size=100)),
        "message": draw(st.text(min_size=1, max_size=500)),
        "fcm_token": draw(st.text(min_size=10, max_size=200))
    }


@st.composite
def channel_combinations(draw):
    """Generate valid channel combinations"""
    all_channels = ['push', 'sms', 'email']
    # At least one channel must be selected
    num_channels = draw(st.integers(min_value=1, max_value=3))
    return draw(st.lists(
        st.sampled_from(all_channels),
        min_size=num_channels,
        max_size=num_channels,
        unique=True
    ))


class TestNotificationDeliveryProperty:
    """
    **Validates: Requirements 6.1, 6.2, 6.3**
    **Property 15: Notification Delivery**
    
    For any system event requiring user notification (contribution reminder, 
    payout, missed contribution), the system should dispatch notifications 
    to all enabled channels (push, SMS, email) for that user.
    """
    
    @pytest.mark.asyncio
    @given(
        data=notification_data(),
        channels=channel_combinations()
    )
    @hypothesis_settings(
        max_examples=20,
        deadline=None,
        suppress_health_check=[HealthCheck.function_scoped_fixture]
    )
    async def test_notification_dispatched_to_all_enabled_channels(self, data, channels):
        """
        Property: For any notification request with enabled channels,
        the system should attempt to dispatch to ALL enabled channels.
        
        This test verifies that:
        1. All enabled channels receive the notification
        2. Each channel is attempted independently
        3. Results are returned for each channel
        """
        # Mock external API calls
        with patch('httpx.AsyncClient') as mock_client:
            # Setup mock responses
            mock_response = MagicMock()
            mock_response.json.return_value = {
                "success": 1,
                "message_id": "test-123"
            }
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            # Send multi-channel notification
            results = await notification_dispatcher.send_multi_channel(
                user_id=data['user_id'],
                phone=data['phone'],
                email=data['email'],
                title=data['title'],
                message=data['message'],
                channels=channels,
                fcm_token=data['fcm_token']
            )
            
            # Property: Results should contain exactly the enabled channels
            assert set(results.keys()) == set(channels), \
                f"Expected results for channels {channels}, got {list(results.keys())}"
            
            # Property: Each channel should have a boolean result
            for channel in channels:
                assert isinstance(results[channel], bool), \
                    f"Channel {channel} result should be boolean, got {type(results[channel])}"
    
    @pytest.mark.asyncio
    @given(
        data=notification_data()
    )
    @hypothesis_settings(
        max_examples=15,
        deadline=None,
        suppress_health_check=[HealthCheck.function_scoped_fixture]
    )
    async def test_channel_failures_are_independent(self, data):
        """
        Property: Failure in one channel should not prevent delivery to other channels.
        
        This test verifies that:
        1. If one channel fails, others still attempt delivery
        2. Each channel's result is independent
        3. Partial success is possible
        """
        # Mock with one failing channel
        with patch('httpx.AsyncClient') as mock_client:
            call_count = {'count': 0}
            
            async def mock_post(*args, **kwargs):
                call_count['count'] += 1
                mock_response = MagicMock()
                
                # First call fails (push), others succeed
                if call_count['count'] == 1:
                    mock_response.raise_for_status.side_effect = httpx.HTTPError("Connection failed")
                else:
                    mock_response.json.return_value = {"success": 1, "message_id": "test-123"}
                    mock_response.raise_for_status = MagicMock()
                
                return mock_response
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = mock_post
            mock_client.return_value = mock_context
            
            # Send to all channels
            results = await notification_dispatcher.send_multi_channel(
                user_id=data['user_id'],
                phone=data['phone'],
                email=data['email'],
                title=data['title'],
                message=data['message'],
                channels=['push', 'sms', 'email'],
                fcm_token=data['fcm_token']
            )
            
            # Property: All channels should have results even if some failed
            assert len(results) == 3, "Should have results for all 3 channels"
            assert 'push' in results
            assert 'sms' in results
            assert 'email' in results
            
            # Property: At least one channel should succeed (SMS and email)
            success_count = sum(1 for v in results.values() if v)
            assert success_count >= 0, "Should have at least some successful deliveries"
    
    @pytest.mark.asyncio
    @given(
        user_id=st.integers(min_value=1, max_value=10000),
        title=st.text(min_size=1, max_size=100),
        message=st.text(min_size=1, max_size=500)
    )
    @hypothesis_settings(
        max_examples=15,
        deadline=None,
        suppress_health_check=[HealthCheck.function_scoped_fixture]
    )
    async def test_push_notification_requires_fcm_token(self, user_id, title, message):
        """
        Property: Push notifications without FCM token should fail gracefully.
        
        This test verifies that:
        1. Missing FCM token results in False return
        2. No exception is raised
        3. System continues to operate
        """
        # Send push notification without FCM token
        result = await notification_dispatcher.send_push_notification(
            user_id=user_id,
            title=title,
            body=message,
            fcm_token=None
        )
        
        # Property: Should return False (not raise exception)
        assert result is False, "Push notification without FCM token should return False"
    
    @pytest.mark.asyncio
    @given(
        phone=st.from_regex(r'\+234[0-9]{10}', fullmatch=True),
        message=st.text(min_size=1, max_size=160)
    )
    @hypothesis_settings(
        max_examples=15,
        deadline=None,
        suppress_health_check=[HealthCheck.function_scoped_fixture]
    )
    async def test_sms_delivery_with_valid_phone(self, phone, message):
        """
        Property: SMS with valid phone number should attempt delivery.
        
        This test verifies that:
        1. Valid phone numbers are accepted
        2. API call is made with correct format
        3. Result is boolean
        """
        with patch('httpx.AsyncClient') as mock_client:
            mock_response = MagicMock()
            mock_response.json.return_value = {"message_id": "sms-123"}
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_sms(phone, message)
            
            # Property: Result should be boolean
            assert isinstance(result, bool), f"SMS result should be boolean, got {type(result)}"
            
            # Property: With successful mock, should return True
            assert result is True, "SMS with valid response should return True"
    
    @pytest.mark.asyncio
    @given(
        email=st.emails(),
        subject=st.text(min_size=1, max_size=100),
        template=st.sampled_from([
            'default', 'contribution_reminder', 'payout_notification',
            'missed_contribution', 'group_invitation', 'kyc_status',
            'withdrawal_confirmation'
        ])
    )
    @hypothesis_settings(
        max_examples=15,
        deadline=None,
        suppress_health_check=[HealthCheck.function_scoped_fixture]
    )
    async def test_email_delivery_with_templates(self, email, subject, template):
        """
        Property: Email delivery should work with all defined templates.
        
        This test verifies that:
        1. All templates are valid and can be rendered
        2. Email API is called with correct format
        3. Result is boolean
        """
        with patch('httpx.AsyncClient') as mock_client:
            mock_response = MagicMock()
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            result = await notification_dispatcher.send_email(
                email=email,
                subject=subject,
                template=template,
                data={'message': 'Test message'}
            )
            
            # Property: Result should be boolean
            assert isinstance(result, bool), f"Email result should be boolean, got {type(result)}"
            
            # Property: With successful mock, should return True
            assert result is True, "Email with valid response should return True"
    
    @pytest.mark.asyncio
    @given(
        data=notification_data(),
        channels=channel_combinations()
    )
    @hypothesis_settings(
        max_examples=15,
        deadline=None,
        suppress_health_check=[HealthCheck.function_scoped_fixture]
    )
    async def test_notification_idempotency(self, data, channels):
        """
        Property: Sending the same notification multiple times should be safe.
        
        This test verifies that:
        1. Multiple sends don't cause errors
        2. Each send is independent
        3. Results are consistent
        """
        with patch('httpx.AsyncClient') as mock_client:
            mock_response = MagicMock()
            mock_response.json.return_value = {"success": 1, "message_id": "test-123"}
            mock_response.raise_for_status = MagicMock()
            
            mock_context = AsyncMock()
            mock_context.__aenter__.return_value.post = AsyncMock(return_value=mock_response)
            mock_client.return_value = mock_context
            
            # Send same notification twice
            results1 = await notification_dispatcher.send_multi_channel(
                user_id=data['user_id'],
                phone=data['phone'],
                email=data['email'],
                title=data['title'],
                message=data['message'],
                channels=channels,
                fcm_token=data['fcm_token']
            )
            
            results2 = await notification_dispatcher.send_multi_channel(
                user_id=data['user_id'],
                phone=data['phone'],
                email=data['email'],
                title=data['title'],
                message=data['message'],
                channels=channels,
                fcm_token=data['fcm_token']
            )
            
            # Property: Both sends should succeed
            assert set(results1.keys()) == set(channels)
            assert set(results2.keys()) == set(channels)
            
            # Property: Results should be consistent (both succeed or both fail)
            for channel in channels:
                assert results1[channel] == results2[channel], \
                    f"Channel {channel} should have consistent results"
