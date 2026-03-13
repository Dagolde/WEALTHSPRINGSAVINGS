"""
Property-Based Tests for Fraud Detection Service
Tests Property 17: Fraud Detection Flagging
"""

import pytest
from hypothesis import given, strategies as st, settings, assume
from unittest.mock import AsyncMock, MagicMock, patch
from datetime import datetime, timedelta
from app.services.fraud.service import FraudDetectionService
from app.services.fraud.models import FraudRuleType, FraudFlagStatus


# Test data generators
@st.composite
def user_activity_data(draw):
    """Generate user activity data for testing"""
    return {
        "user_id": draw(st.integers(min_value=1, max_value=10000)),
        "type": draw(st.sampled_from([
            "payment", "withdrawal", "registration", "contribution"
        ])),
        "risk_score": draw(st.integers(min_value=0, max_value=100)),
        "rule_type": draw(st.sampled_from(list(FraudRuleType))),
        "details": {
            "amount": draw(st.floats(min_value=100, max_value=100000)),
            "timestamp": datetime.utcnow().isoformat()
        },
        "triggered_rules": draw(st.lists(
            st.sampled_from([rule.value for rule in FraudRuleType]),
            min_size=0,
            max_size=3
        ))
    }


@st.composite
def payment_data(draw):
    """Generate payment data for fraud checking"""
    return {
        "user_id": draw(st.integers(min_value=1, max_value=10000)),
        "amount": draw(st.floats(min_value=100, max_value=100000)),
        "payment_method": draw(st.sampled_from(["wallet", "card", "bank_transfer"])),
        "metadata": {
            "device_id": draw(st.text(min_size=10, max_size=50)),
            "ip_address": draw(st.from_regex(r"^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$", fullmatch=True))
        }
    }


@st.composite
def withdrawal_data(draw):
    """Generate withdrawal data for fraud checking"""
    return {
        "user_id": draw(st.integers(min_value=1, max_value=10000)),
        "amount": draw(st.floats(min_value=100, max_value=100000)),
        "bank_account": draw(st.from_regex(r"^\d{10}$", fullmatch=True))
    }


class TestFraudDetectionProperties:
    """
    Property-Based Tests for Fraud Detection
    
    **Validates: Requirements 8.1, 8.2**
    **Property 17: Fraud Detection Flagging**
    
    For any activity matching fraud detection rules (multiple failed payments,
    duplicate accounts, suspicious patterns), the system should create a flag
    record for admin review.
    """
    
    @pytest.mark.asyncio
    @given(activity=user_activity_data())
    @settings(max_examples=100, deadline=None)
    async def test_property_17_fraud_flagging_creates_record(self, activity):
        """
        Property 17: Fraud Detection Flagging
        
        For any suspicious activity with risk score > 0, the system should
        create a fraud flag record for admin review.
        
        **Validates: Design Property 17**
        """
        # Arrange
        mock_db = AsyncMock()
        mock_db.add = MagicMock()
        mock_db.commit = AsyncMock()
        mock_db.rollback = AsyncMock()
        
        service = FraudDetectionService(db=mock_db)
        
        # Assume we have a risky activity
        assume(activity["risk_score"] > 0)
        
        # Act
        result = await service.flag_suspicious_activity(activity)
        
        # Assert - Property: Flagging should succeed
        assert result is True, "Suspicious activity should be flagged successfully"
        
        # Assert - Property: Database record should be created
        mock_db.add.assert_called_once()
        mock_db.commit.assert_called_once()
        
        # Assert - Property: The flagged record should have correct attributes
        flagged_record = mock_db.add.call_args[0][0]
        assert flagged_record.user_id == activity["user_id"]
        assert flagged_record.risk_score == activity["risk_score"]
        assert flagged_record.status == FraudFlagStatus.PENDING
        assert flagged_record.activity_type == activity["type"]
    
    @pytest.mark.asyncio
    @given(
        user_id=st.integers(min_value=1, max_value=10000),
        failed_count=st.integers(min_value=0, max_value=10)
    )
    @settings(max_examples=100, deadline=None)
    async def test_property_17_multiple_failed_payments_detection(self, user_id, failed_count):
        """
        Property 17: Multiple Failed Payments Rule
        
        For any user with >3 failed payment attempts in 1 hour, the system
        should flag this as suspicious activity.
        
        **Validates: Design Property 17**
        """
        # Arrange
        mock_db = AsyncMock()
        service = FraudDetectionService(db=mock_db)
        
        # Mock cache to return failed payment count
        with patch('app.services.fraud.service.cache') as mock_cache:
            mock_cache.get = AsyncMock(return_value=str(failed_count))
            mock_cache.set = AsyncMock()
            
            # Act
            result = await service._check_failed_payments(user_id)
            
            # Assert - Property: Rule triggers when threshold exceeded
            if failed_count > 3:
                assert result["triggered"] is True
                assert result["risk_score"] == 30
                assert result["rule"] == FraudRuleType.MULTIPLE_FAILED_PAYMENTS.value
            else:
                assert result["triggered"] is False
                assert result["risk_score"] == 0
    
    @pytest.mark.asyncio
    @given(payment=payment_data())
    @settings(max_examples=100, deadline=None)
    async def test_property_17_payment_fraud_check_returns_assessment(self, payment):
        """
        Property 17: Payment Fraud Assessment
        
        For any payment, the fraud detection system should return a risk
        assessment with score, flags, and recommendation.
        
        **Validates: Design Property 17**
        """
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database queries
        mock_result = MagicMock()
        mock_result.fetchone = MagicMock(return_value=(0,))
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        
        # Mock cache
        with patch('app.services.fraud.service.cache') as mock_cache:
            mock_cache.get = AsyncMock(return_value="0")
            mock_cache.set = AsyncMock()
            
            # Act
            result = await service.check_payment_fraud(payment)
            
            # Assert - Property: Assessment must contain required fields
            assert "risk_score" in result
            assert "flags" in result
            assert "recommendation" in result
            assert "checked_at" in result
            
            # Assert - Property: Risk score must be non-negative
            assert result["risk_score"] >= 0
            
            # Assert - Property: Recommendation must be valid
            assert result["recommendation"] in ["approve", "review", "suspend"]
            
            # Assert - Property: Flags must be a list
            assert isinstance(result["flags"], list)
    
    @pytest.mark.asyncio
    @given(withdrawal=withdrawal_data())
    @settings(max_examples=100, deadline=None)
    async def test_property_17_withdrawal_fraud_check_returns_assessment(self, withdrawal):
        """
        Property 17: Withdrawal Fraud Assessment
        
        For any withdrawal request, the fraud detection system should return
        a risk assessment with appropriate flags.
        
        **Validates: Design Property 17**
        """
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database queries to return safe values
        mock_result = MagicMock()
        mock_result.fetchone = MagicMock(return_value=(1,))  # Has previous withdrawals
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        
        # Act
        result = await service.check_withdrawal_fraud(
            user_id=withdrawal["user_id"],
            amount=withdrawal["amount"],
            bank_account=withdrawal["bank_account"]
        )
        
        # Assert - Property: Assessment must contain required fields
        assert "risk_score" in result
        assert "flags" in result
        assert "recommendation" in result
        assert "checked_at" in result
        
        # Assert - Property: Risk score must be non-negative
        assert result["risk_score"] >= 0
        
        # Assert - Property: Recommendation must be valid
        assert result["recommendation"] in ["approve", "review", "suspend"]
    
    @pytest.mark.asyncio
    @given(
        risk_score=st.integers(min_value=0, max_value=100)
    )
    @settings(max_examples=100, deadline=None)
    async def test_property_17_recommendation_based_on_risk_score(self, risk_score):
        """
        Property 17: Risk Score Recommendation Mapping
        
        For any risk score, the system should provide consistent recommendations:
        - score < 50: approve
        - 50 <= score < 80: review
        - score >= 80: suspend
        
        **Validates: Design Property 17**
        """
        # Arrange
        service = FraudDetectionService()
        
        # Act
        recommendation = service._get_recommendation(risk_score)
        
        # Assert - Property: Recommendation matches risk score thresholds
        if risk_score >= 80:
            assert recommendation == "suspend"
        elif risk_score >= 50:
            assert recommendation == "review"
        else:
            assert recommendation == "approve"
    
    @pytest.mark.asyncio
    @given(
        user_id=st.integers(min_value=1, max_value=10000)
    )
    @settings(max_examples=100, deadline=None)
    async def test_property_17_user_behavior_analysis_aggregates_rules(self, user_id):
        """
        Property 17: User Behavior Analysis Aggregation
        
        For any user, behavior analysis should aggregate risk scores from
        multiple fraud detection rules.
        
        **Validates: Design Property 17**
        """
        # Arrange
        mock_db = AsyncMock()
        
        # Mock database queries
        mock_result = MagicMock()
        mock_result.fetchone = MagicMock(return_value=(0,))
        mock_result.fetchall = MagicMock(return_value=[])
        mock_db.execute = AsyncMock(return_value=mock_result)
        
        service = FraudDetectionService(db=mock_db)
        
        # Mock cache
        with patch('app.services.fraud.service.cache') as mock_cache:
            mock_cache.get = AsyncMock(return_value="0")
            mock_cache.set = AsyncMock()
            
            # Act
            result = await service.analyze_user_behavior(user_id)
            
            # Assert - Property: Analysis must contain required fields
            assert "user_id" in result
            assert result["user_id"] == user_id
            assert "risk_score" in result
            assert "flags" in result
            assert "recommendation" in result
            assert "analysis_date" in result
            
            # Assert - Property: Risk score is sum of triggered rules
            total_risk = sum(flag["risk_score"] for flag in result["flags"])
            assert result["risk_score"] == total_risk
            
            # Assert - Property: Flags is a list
            assert isinstance(result["flags"], list)
    
    @pytest.mark.asyncio
    @given(
        activities=st.lists(
            user_activity_data(),
            min_size=1,
            max_size=5
        )
    )
    @settings(max_examples=50, deadline=None)
    async def test_property_17_multiple_flags_for_same_user(self, activities):
        """
        Property 17: Multiple Fraud Flags
        
        For any user with multiple suspicious activities, the system should
        create separate flag records for each activity.
        
        **Validates: Design Property 17**
        """
        # Arrange
        mock_db = AsyncMock()
        mock_db.add = MagicMock()
        mock_db.commit = AsyncMock()
        
        # Use same user_id for all activities
        user_id = activities[0]["user_id"]
        for activity in activities:
            activity["user_id"] = user_id
            assume(activity["risk_score"] > 0)
        
        service = FraudDetectionService(db=mock_db)
        
        # Act - Flag each activity
        results = []
        for activity in activities:
            result = await service.flag_suspicious_activity(activity)
            results.append(result)
        
        # Assert - Property: All activities should be flagged
        assert all(results), "All suspicious activities should be flagged"
        
        # Assert - Property: Number of flags equals number of activities
        assert mock_db.add.call_count == len(activities)
        assert mock_db.commit.call_count == len(activities)
