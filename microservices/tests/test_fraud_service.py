"""
Unit Tests for Fraud Detection Service
Tests specific fraud detection rules and scenarios
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from datetime import datetime, timedelta
from app.services.fraud.service import FraudDetectionService
from app.services.fraud.models import FraudRuleType, FraudFlagStatus


class TestFraudDetectionService:
    """Unit tests for fraud detection service"""
    
    @pytest.mark.asyncio
    async def test_multiple_failed_payments_triggers_flag(self):
        """Test that >3 failed payments in 1 hour triggers fraud flag"""
        # Arrange
        mock_db = AsyncMock()
        service = FraudDetectionService(db=mock_db)
        user_id = 123
        
        # Mock cache to return 4 failed payments
        with patch('app.services.fraud.service.cache') as mock_cache:
            mock_cache.get = AsyncMock(return_value="4")
            mock_cache.set = AsyncMock()
            
            # Act
            result = await service._check_failed_payments(user_id)
            
            # Assert
            assert result["triggered"] is True
            assert result["risk_score"] == 30
            assert result["rule"] == FraudRuleType.MULTIPLE_FAILED_PAYMENTS.value
            assert result["details"]["failed_count"] == 4
    
    @pytest.mark.asyncio
    async def test_failed_payments_below_threshold_no_flag(self):
        """Test that <=3 failed payments does not trigger flag"""
        # Arrange
        mock_db = AsyncMock()
        service = FraudDetectionService(db=mock_db)
        user_id = 123
        
        # Mock cache to return 2 failed payments
        with patch('app.services.fraud.service.cache') as mock_cache:
            mock_cache.get = AsyncMock(return_value="2")
            mock_cache.set = AsyncMock()
            
            # Act
            result = await service._check_failed_payments(user_id)
            
            # Assert
            assert result["triggered"] is False
            assert result["risk_score"] == 0
    
    @pytest.mark.asyncio
    async def test_duplicate_bank_account_detection(self):
        """Test detection of bank accounts used by multiple users"""
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database to return duplicate accounts
        mock_result = MagicMock()
        mock_result.fetchall = MagicMock(return_value=[
            ("1234567890", 3),  # Account used by 3 users
            ("0987654321", 2)   # Account used by 2 users
        ])
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        user_id = 123
        
        # Act
        result = await service._check_duplicate_bank_accounts(user_id)
        
        # Assert
        assert result["triggered"] is True
        assert result["risk_score"] == 40
        assert result["rule"] == FraudRuleType.DUPLICATE_BANK_ACCOUNT.value
        assert result["details"]["duplicate_accounts"] == 2
    
    @pytest.mark.asyncio
    async def test_unusual_withdrawal_pattern_detection(self):
        """Test detection of unusually large withdrawals"""
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database to return withdrawal history
        # Average: (50000 + 5000 + 4000 + 6000) / 4 = 16250
        # Latest: 50000 (3.08x average and > 10000)
        mock_result = MagicMock()
        mock_result.fetchall = MagicMock(return_value=[
            (50000, datetime.utcnow()),  # Latest withdrawal
            (5000, datetime.utcnow() - timedelta(days=1)),
            (4000, datetime.utcnow() - timedelta(days=2)),
            (6000, datetime.utcnow() - timedelta(days=3))
        ])
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        user_id = 123
        
        # Act
        result = await service._check_withdrawal_patterns(user_id)
        
        # Assert
        assert result["triggered"] is True
        assert result["risk_score"] == 25
        assert result["rule"] == FraudRuleType.UNUSUAL_WITHDRAWAL.value
        assert result["details"]["latest_amount"] == 50000
    
    @pytest.mark.asyncio
    async def test_contribution_anomaly_detection(self):
        """Test detection of rapid contributions to multiple groups"""
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database to return 6 groups joined in 24 hours
        mock_result = MagicMock()
        mock_result.fetchone = MagicMock(return_value=(6, 8))  # 6 groups, 8 contributions
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        user_id = 123
        
        # Act
        result = await service._check_contribution_anomalies(user_id)
        
        # Assert
        assert result["triggered"] is True
        assert result["risk_score"] == 20
        assert result["rule"] == FraudRuleType.CONTRIBUTION_ANOMALY.value
        assert result["details"]["groups_joined_24h"] == 6
    
    @pytest.mark.asyncio
    async def test_fraud_score_calculation(self):
        """Test that fraud score is correctly calculated from multiple rules"""
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database queries
        mock_result = MagicMock()
        mock_result.fetchone = MagicMock(return_value=(0,))
        mock_result.fetchall = MagicMock(return_value=[])
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        user_id = 123
        
        # Mock cache to trigger failed payments rule
        with patch('app.services.fraud.service.cache') as mock_cache:
            mock_cache.get = AsyncMock(return_value="5")  # 5 failed payments
            mock_cache.set = AsyncMock()
            
            # Act
            result = await service.analyze_user_behavior(user_id)
            
            # Assert
            assert result["risk_score"] >= 30  # At least from failed payments
            assert len(result["flags"]) >= 1
            assert result["user_id"] == user_id
    
    @pytest.mark.asyncio
    async def test_recommendation_approve_low_risk(self):
        """Test that low risk scores get 'approve' recommendation"""
        # Arrange
        service = FraudDetectionService()
        
        # Act
        recommendation = service._get_recommendation(25)
        
        # Assert
        assert recommendation == "approve"
    
    @pytest.mark.asyncio
    async def test_recommendation_review_medium_risk(self):
        """Test that medium risk scores get 'review' recommendation"""
        # Arrange
        service = FraudDetectionService()
        
        # Act
        recommendation = service._get_recommendation(60)
        
        # Assert
        assert recommendation == "review"
    
    @pytest.mark.asyncio
    async def test_recommendation_suspend_high_risk(self):
        """Test that high risk scores get 'suspend' recommendation"""
        # Arrange
        service = FraudDetectionService()
        
        # Act
        recommendation = service._get_recommendation(85)
        
        # Assert
        assert recommendation == "suspend"
    
    @pytest.mark.asyncio
    async def test_flag_suspicious_activity_creates_record(self):
        """Test that flagging creates a database record"""
        # Arrange
        mock_db = AsyncMock()
        mock_db.add = MagicMock()
        mock_db.commit = AsyncMock()
        
        service = FraudDetectionService(db=mock_db)
        
        activity = {
            "user_id": 123,
            "type": "payment",
            "risk_score": 75,
            "rule_type": FraudRuleType.MULTIPLE_FAILED_PAYMENTS,
            "details": {"amount": 5000},
            "triggered_rules": ["multiple_failed_payments"]
        }
        
        # Act
        result = await service.flag_suspicious_activity(activity)
        
        # Assert
        assert result is True
        mock_db.add.assert_called_once()
        mock_db.commit.assert_called_once()
        
        # Check the flagged record
        flagged_record = mock_db.add.call_args[0][0]
        assert flagged_record.user_id == 123
        assert flagged_record.risk_score == 75
        assert flagged_record.status == FraudFlagStatus.PENDING
    
    @pytest.mark.asyncio
    async def test_withdrawal_fraud_check_new_account(self):
        """Test withdrawal to new bank account triggers flag"""
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database to return 0 previous withdrawals (new account)
        mock_result = MagicMock()
        mock_result.fetchone = MagicMock(return_value=(0,))
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        
        # Act
        result = await service.check_withdrawal_fraud(
            user_id=123,
            amount=10000,
            bank_account="1234567890"
        )
        
        # Assert
        assert result["risk_score"] >= 15  # New account flag
        assert any(flag["rule"] == "new_account" for flag in result["flags"])
    
    @pytest.mark.asyncio
    async def test_withdrawal_fraud_check_shared_account(self):
        """Test withdrawal to shared bank account triggers flag"""
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database queries
        def mock_execute_side_effect(query, params):
            mock_result = MagicMock()
            # First query: withdrawal count
            if "COUNT(*)" in str(query):
                mock_result.fetchone = MagicMock(return_value=(1,))
            # Last query: shared account check
            elif "COUNT(DISTINCT user_id)" in str(query):
                mock_result.fetchone = MagicMock(return_value=(3,))  # 3 users
            else:
                mock_result.fetchone = MagicMock(return_value=(5000,))
            return mock_result
        
        mock_db.execute = AsyncMock(side_effect=mock_execute_side_effect)
        
        service = FraudDetectionService(db=mock_db)
        
        # Act
        result = await service.check_withdrawal_fraud(
            user_id=123,
            amount=10000,
            bank_account="1234567890"
        )
        
        # Assert
        assert result["risk_score"] >= 40  # Shared account flag
        assert any(flag["rule"] == "shared_account" for flag in result["flags"])
    
    @pytest.mark.asyncio
    async def test_payment_fraud_check_rapid_payments(self):
        """Test rapid successive payments triggers flag"""
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database queries
        mock_result = MagicMock()
        mock_result.fetchone = MagicMock(return_value=(1000,))  # Average amount
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        
        payment_data = {
            "user_id": 123,
            "amount": 1500,
            "payment_method": "card",
            "metadata": {}
        }
        
        # Mock cache to show 4 payments in 5 minutes
        with patch('app.services.fraud.service.cache') as mock_cache:
            mock_cache.get = AsyncMock(side_effect=["0", "4"])  # Failed payments, then rapid payments
            mock_cache.set = AsyncMock()
            
            # Act
            result = await service.check_payment_fraud(payment_data)
            
            # Assert
            assert result["risk_score"] >= 25  # Rapid payments flag
            assert any(flag["rule"] == "rapid_payments" for flag in result["flags"])
    
    @pytest.mark.asyncio
    async def test_duplicate_account_detection_by_device(self):
        """Test duplicate account detection by device ID"""
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database to return users with same device
        mock_result = MagicMock()
        mock_result.fetchall = MagicMock(return_value=[
            (101,), (102,), (103,)  # 3 users with same device
        ])
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        
        user_data = {
            "email": "test@example.com",
            "phone": "+2348012345678",
            "device_id": "device123",
            "ip_address": None
        }
        
        # Act
        duplicates = await service.detect_duplicate_accounts(user_data)
        
        # Assert
        assert len(duplicates) == 3
        assert 101 in duplicates
        assert 102 in duplicates
        assert 103 in duplicates
