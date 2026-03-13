"""
Fraud Detection Service Implementation
Analyzes patterns and detects suspicious activities
"""

from typing import Dict, List, Any, Optional
from datetime import datetime, timedelta
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func, and_, or_
from sqlalchemy.sql import text
import logging

from app.services.fraud.models import (
    FraudFlag, FraudPattern, FraudRule, 
    FraudRuleType, FraudFlagStatus
)
from app.redis_client import cache

logger = logging.getLogger(__name__)


class FraudDetectionService:
    """Fraud detection and prevention service"""
    
    def __init__(self, db: Optional[AsyncSession] = None):
        self.db = db
        self.risk_threshold_review = 50
        self.risk_threshold_suspend = 80
    
    async def analyze_user_behavior(self, user_id: int) -> Dict[str, Any]:
        """
        Analyze user behavior patterns for anomalies
        
        Args:
            user_id: User ID to analyze
        
        Returns:
            Dict containing risk score and flagged patterns
        """
        try:
            flags = []
            risk_score = 0
            
            # Check for multiple failed payment attempts
            failed_payments_result = await self._check_failed_payments(user_id)
            if failed_payments_result["triggered"]:
                flags.append(failed_payments_result)
                risk_score += failed_payments_result["risk_score"]
            
            # Check for unusual withdrawal patterns
            withdrawal_result = await self._check_withdrawal_patterns(user_id)
            if withdrawal_result["triggered"]:
                flags.append(withdrawal_result)
                risk_score += withdrawal_result["risk_score"]
            
            # Check for duplicate bank accounts
            duplicate_result = await self._check_duplicate_bank_accounts(user_id)
            if duplicate_result["triggered"]:
                flags.append(duplicate_result)
                risk_score += duplicate_result["risk_score"]
            
            # Check for contribution anomalies
            contribution_result = await self._check_contribution_anomalies(user_id)
            if contribution_result["triggered"]:
                flags.append(contribution_result)
                risk_score += contribution_result["risk_score"]
            
            logger.info(f"User behavior analyzed for user {user_id}: risk_score={risk_score}")
            
            return {
                "user_id": user_id,
                "risk_score": risk_score,
                "flags": flags,
                "recommendation": self._get_recommendation(risk_score),
                "analysis_date": datetime.utcnow().isoformat()
            }
        
        except Exception as e:
            logger.error(f"Error analyzing user behavior: {e}")
            raise
    
    async def check_payment_fraud(self, payment_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Check payment for fraud indicators
        
        Args:
            payment_data: Payment transaction data
        
        Returns:
            Dict containing fraud assessment
        """
        try:
            flags = []
            risk_score = 0
            user_id = payment_data.get("user_id")
            amount = payment_data.get("amount", 0)
            
            # Rule 1: Multiple failed payment attempts
            failed_result = await self._check_failed_payments(user_id)
            if failed_result["triggered"]:
                flags.append(failed_result)
                risk_score += failed_result["risk_score"]
            
            # Rule 2: Unusual payment amount
            if self.db:
                query = text("""
                    SELECT AVG(amount) as avg_amount
                    FROM contributions
                    WHERE user_id = :user_id
                    AND payment_status = 'successful'
                    AND created_at >= :thirty_days_ago
                """)
                thirty_days_ago = datetime.utcnow() - timedelta(days=30)
                result = await self.db.execute(
                    query,
                    {"user_id": user_id, "thirty_days_ago": thirty_days_ago}
                )
                row = result.fetchone()
                avg_amount = float(row[0]) if row and row[0] else 0
                
                if avg_amount > 0 and amount > (avg_amount * 3):
                    flags.append({
                        "rule": "unusual_amount",
                        "triggered": True,
                        "risk_score": 20,
                        "details": {"amount": amount, "average": avg_amount}
                    })
                    risk_score += 20
            
            # Rule 3: Rapid successive payments
            cache_key = f"fraud:payment_count:{user_id}"
            cached_count = await cache.get(cache_key)
            payment_count = int(cached_count) if cached_count else 0
            
            if payment_count > 3:
                flags.append({
                    "rule": "rapid_payments",
                    "triggered": True,
                    "risk_score": 25,
                    "details": {"payments_in_5min": payment_count}
                })
                risk_score += 25
            
            # Increment payment counter
            await cache.set(cache_key, str(payment_count + 1), expire=300)
            
            logger.info(f"Payment fraud check completed: risk_score={risk_score}")
            
            return {
                "risk_score": risk_score,
                "flags": flags,
                "recommendation": self._get_recommendation(risk_score),
                "checked_at": datetime.utcnow().isoformat()
            }
        
        except Exception as e:
            logger.error(f"Error checking payment fraud: {e}")
            raise
    
    async def detect_duplicate_accounts(self, user_data: Dict[str, Any]) -> List[int]:
        """
        Detect potential duplicate accounts
        
        Args:
            user_data: User registration data
        
        Returns:
            List of potentially duplicate user IDs
        """
        try:
            duplicates = []
            
            if not self.db:
                return duplicates
            
            email = user_data.get("email")
            phone = user_data.get("phone")
            device_id = user_data.get("device_id")
            ip_address = user_data.get("ip_address")
            
            # Check for same device ID
            if device_id:
                query = text("""
                    SELECT DISTINCT user_id
                    FROM audit_logs
                    WHERE action = 'user_login'
                    AND new_values->>'device_id' = :device_id
                    AND created_at >= :thirty_days_ago
                    LIMIT 10
                """)
                thirty_days_ago = datetime.utcnow() - timedelta(days=30)
                result = await self.db.execute(
                    query,
                    {"device_id": device_id, "thirty_days_ago": thirty_days_ago}
                )
                device_users = [row[0] for row in result.fetchall()]
                duplicates.extend(device_users)
            
            # Check for same IP address (multiple accounts)
            if ip_address:
                query = text("""
                    SELECT DISTINCT user_id
                    FROM audit_logs
                    WHERE ip_address = :ip_address
                    AND created_at >= :seven_days_ago
                    GROUP BY user_id
                    HAVING COUNT(*) > 5
                    LIMIT 10
                """)
                seven_days_ago = datetime.utcnow() - timedelta(days=7)
                result = await self.db.execute(
                    query,
                    {"ip_address": ip_address, "seven_days_ago": seven_days_ago}
                )
                ip_users = [row[0] for row in result.fetchall()]
                duplicates.extend(ip_users)
            
            # Remove duplicates from list
            duplicates = list(set(duplicates))
            
            logger.info(f"Duplicate account check completed: {len(duplicates)} found")
            
            return duplicates
        
        except Exception as e:
            logger.error(f"Error detecting duplicate accounts: {e}")
            raise
    
    async def flag_suspicious_activity(
        self,
        activity: Dict[str, Any]
    ) -> bool:
        """
        Flag suspicious activity for admin review
        
        Args:
            activity: Activity data to flag
        
        Returns:
            True if flagged successfully
        """
        try:
            if not self.db:
                logger.warning("Database not available, cannot flag activity")
                return False
            
            user_id = activity.get("user_id")
            activity_type = activity.get("type")
            risk_score = activity.get("risk_score", 0)
            details = activity.get("details", {})
            triggered_rules = activity.get("triggered_rules", [])
            
            # Create fraud flag record
            fraud_flag = FraudFlag(
                user_id=user_id,
                rule_type=activity.get("rule_type", FraudRuleType.SUSPICIOUS_PATTERN),
                risk_score=risk_score,
                status=FraudFlagStatus.PENDING,
                activity_type=activity_type,
                activity_data=details,
                triggered_rules=triggered_rules,
                detection_metadata={
                    "flagged_at": datetime.utcnow().isoformat(),
                    "auto_flagged": True
                }
            )
            
            self.db.add(fraud_flag)
            await self.db.commit()
            
            logger.warning(
                f"Suspicious activity flagged: user_id={user_id}, "
                f"type={activity_type}, risk_score={risk_score}"
            )
            
            return True
        
        except Exception as e:
            logger.error(f"Error flagging suspicious activity: {e}")
            if self.db:
                await self.db.rollback()
            raise
    
    async def check_withdrawal_fraud(
        self,
        user_id: int,
        amount: float,
        bank_account: str
    ) -> Dict[str, Any]:
        """
        Check withdrawal request for fraud indicators
        
        Args:
            user_id: User ID
            amount: Withdrawal amount
            bank_account: Bank account number
        
        Returns:
            Dict containing fraud assessment
        """
        try:
            flags = []
            risk_score = 0
            
            if not self.db:
                return {
                    "risk_score": 0,
                    "flags": [],
                    "recommendation": "approve",
                    "checked_at": datetime.utcnow().isoformat()
                }
            
            # Rule 1: First withdrawal to new account
            query = text("""
                SELECT COUNT(*) as count
                FROM withdrawals
                WHERE user_id = :user_id
                AND bank_account_id IN (
                    SELECT id FROM bank_accounts 
                    WHERE user_id = :user_id AND account_number = :account_number
                )
                AND status = 'successful'
            """)
            result = await self.db.execute(
                query,
                {"user_id": user_id, "account_number": bank_account}
            )
            row = result.fetchone()
            withdrawal_count = row[0] if row else 0
            
            if withdrawal_count == 0:
                flags.append({
                    "rule": "new_account",
                    "triggered": True,
                    "risk_score": 15,
                    "details": {"first_withdrawal": True}
                })
                risk_score += 15
            
            # Rule 2: Large withdrawal amount
            query = text("""
                SELECT AVG(amount) as avg_amount
                FROM withdrawals
                WHERE user_id = :user_id
                AND status = 'successful'
                AND created_at >= :thirty_days_ago
            """)
            thirty_days_ago = datetime.utcnow() - timedelta(days=30)
            result = await self.db.execute(
                query,
                {"user_id": user_id, "thirty_days_ago": thirty_days_ago}
            )
            row = result.fetchone()
            avg_withdrawal = float(row[0]) if row and row[0] else 0
            
            if avg_withdrawal > 0 and amount > (avg_withdrawal * 2):
                flags.append({
                    "rule": "large_amount",
                    "triggered": True,
                    "risk_score": 25,
                    "details": {"amount": amount, "average": avg_withdrawal}
                })
                risk_score += 25
            
            # Rule 3: Rapid withdrawal after deposit
            query = text("""
                SELECT MAX(created_at) as last_deposit
                FROM wallet_transactions
                WHERE user_id = :user_id
                AND type = 'credit'
                AND created_at >= :twenty_four_hours_ago
            """)
            twenty_four_hours_ago = datetime.utcnow() - timedelta(hours=24)
            result = await self.db.execute(
                query,
                {"user_id": user_id, "twenty_four_hours_ago": twenty_four_hours_ago}
            )
            row = result.fetchone()
            
            if row and row[0]:
                flags.append({
                    "rule": "rapid_withdrawal",
                    "triggered": True,
                    "risk_score": 30,
                    "details": {"withdrawal_within_24h": True}
                })
                risk_score += 30
            
            # Rule 4: Bank account used by multiple users
            query = text("""
                SELECT COUNT(DISTINCT user_id) as user_count
                FROM bank_accounts
                WHERE account_number = :account_number
            """)
            result = await self.db.execute(query, {"account_number": bank_account})
            row = result.fetchone()
            user_count = row[0] if row else 0
            
            if user_count > 1:
                flags.append({
                    "rule": "shared_account",
                    "triggered": True,
                    "risk_score": 40,
                    "details": {"user_count": user_count}
                })
                risk_score += 40
            
            logger.info(f"Withdrawal fraud check completed: risk_score={risk_score}")
            
            return {
                "risk_score": risk_score,
                "flags": flags,
                "recommendation": self._get_recommendation(risk_score),
                "checked_at": datetime.utcnow().isoformat()
            }
        
        except Exception as e:
            logger.error(f"Error checking withdrawal fraud: {e}")
            raise


    async def _check_failed_payments(self, user_id: int) -> Dict[str, Any]:
        """Check for multiple failed payment attempts (>3 in 1 hour)"""
        try:
            # Check Redis cache first
            cache_key = f"fraud:failed_payments:{user_id}"
            cached_count = await cache.get(cache_key)
            
            if cached_count:
                failed_count = int(cached_count)
            else:
                # Query database for failed payments in last hour
                if self.db:
                    one_hour_ago = datetime.utcnow() - timedelta(hours=1)
                    query = text("""
                        SELECT COUNT(*) as count
                        FROM contributions
                        WHERE user_id = :user_id
                        AND payment_status = 'failed'
                        AND created_at >= :one_hour_ago
                    """)
                    result = await self.db.execute(
                        query,
                        {"user_id": user_id, "one_hour_ago": one_hour_ago}
                    )
                    row = result.fetchone()
                    failed_count = row[0] if row else 0
                    
                    # Cache for 5 minutes
                    await cache.set(cache_key, str(failed_count), expire=300)
                else:
                    failed_count = 0
            
            triggered = failed_count > 3
            
            return {
                "rule": FraudRuleType.MULTIPLE_FAILED_PAYMENTS.value,
                "triggered": triggered,
                "risk_score": 30 if triggered else 0,
                "details": {
                    "failed_count": failed_count,
                    "threshold": 3,
                    "time_window": "1 hour"
                }
            }
        
        except Exception as e:
            logger.error(f"Error checking failed payments: {e}")
            return {"rule": FraudRuleType.MULTIPLE_FAILED_PAYMENTS.value, "triggered": False, "risk_score": 0}
    
    async def _check_withdrawal_patterns(self, user_id: int) -> Dict[str, Any]:
        """Check for unusual withdrawal patterns"""
        try:
            if not self.db:
                return {"rule": FraudRuleType.UNUSUAL_WITHDRAWAL.value, "triggered": False, "risk_score": 0}
            
            # Get user's withdrawal history
            query = text("""
                SELECT amount, created_at
                FROM withdrawals
                WHERE user_id = :user_id
                AND status IN ('successful', 'processing')
                AND created_at >= :thirty_days_ago
                ORDER BY created_at DESC
            """)
            
            thirty_days_ago = datetime.utcnow() - timedelta(days=30)
            result = await self.db.execute(
                query,
                {"user_id": user_id, "thirty_days_ago": thirty_days_ago}
            )
            withdrawals = result.fetchall()
            
            if len(withdrawals) < 2:
                return {"rule": FraudRuleType.UNUSUAL_WITHDRAWAL.value, "triggered": False, "risk_score": 0}
            
            # Calculate average withdrawal amount
            amounts = [float(w[0]) for w in withdrawals]
            avg_amount = sum(amounts) / len(amounts)
            latest_amount = amounts[0]
            
            # Flag if latest withdrawal is 3x average
            triggered = latest_amount > (avg_amount * 3) and latest_amount > 10000
            
            return {
                "rule": FraudRuleType.UNUSUAL_WITHDRAWAL.value,
                "triggered": triggered,
                "risk_score": 25 if triggered else 0,
                "details": {
                    "latest_amount": latest_amount,
                    "average_amount": avg_amount,
                    "threshold_multiplier": 3
                }
            }
        
        except Exception as e:
            logger.error(f"Error checking withdrawal patterns: {e}")
            return {"rule": FraudRuleType.UNUSUAL_WITHDRAWAL.value, "triggered": False, "risk_score": 0}
    
    async def _check_duplicate_bank_accounts(self, user_id: int) -> Dict[str, Any]:
        """Check for duplicate bank account usage across multiple users"""
        try:
            if not self.db:
                return {"rule": FraudRuleType.DUPLICATE_BANK_ACCOUNT.value, "triggered": False, "risk_score": 0}
            
            # Check if user's bank accounts are used by other users
            query = text("""
                SELECT ba1.account_number, COUNT(DISTINCT ba1.user_id) as user_count
                FROM bank_accounts ba1
                WHERE ba1.account_number IN (
                    SELECT account_number FROM bank_accounts WHERE user_id = :user_id
                )
                GROUP BY ba1.account_number
                HAVING COUNT(DISTINCT ba1.user_id) > 1
            """)
            
            result = await self.db.execute(query, {"user_id": user_id})
            duplicates = result.fetchall()
            
            triggered = len(duplicates) > 0
            
            return {
                "rule": FraudRuleType.DUPLICATE_BANK_ACCOUNT.value,
                "triggered": triggered,
                "risk_score": 40 if triggered else 0,
                "details": {
                    "duplicate_accounts": len(duplicates),
                    "accounts": [{"account_number": d[0], "user_count": d[1]} for d in duplicates]
                }
            }
        
        except Exception as e:
            logger.error(f"Error checking duplicate bank accounts: {e}")
            return {"rule": FraudRuleType.DUPLICATE_BANK_ACCOUNT.value, "triggered": False, "risk_score": 0}
    
    async def _check_contribution_anomalies(self, user_id: int) -> Dict[str, Any]:
        """Check for contribution pattern anomalies"""
        try:
            if not self.db:
                return {"rule": FraudRuleType.CONTRIBUTION_ANOMALY.value, "triggered": False, "risk_score": 0}
            
            # Check for rapid contributions to multiple groups
            query = text("""
                SELECT COUNT(DISTINCT group_id) as group_count,
                       COUNT(*) as contribution_count
                FROM contributions
                WHERE user_id = :user_id
                AND created_at >= :one_day_ago
                AND payment_status = 'successful'
            """)
            
            one_day_ago = datetime.utcnow() - timedelta(days=1)
            result = await self.db.execute(query, {"user_id": user_id, "one_day_ago": one_day_ago})
            row = result.fetchone()
            
            group_count = row[0] if row else 0
            contribution_count = row[1] if row else 0
            
            # Flag if user joined and contributed to >5 groups in 24 hours
            triggered = group_count > 5
            
            return {
                "rule": FraudRuleType.CONTRIBUTION_ANOMALY.value,
                "triggered": triggered,
                "risk_score": 20 if triggered else 0,
                "details": {
                    "groups_joined_24h": group_count,
                    "contributions_24h": contribution_count,
                    "threshold": 5
                }
            }
        
        except Exception as e:
            logger.error(f"Error checking contribution anomalies: {e}")
            return {"rule": FraudRuleType.CONTRIBUTION_ANOMALY.value, "triggered": False, "risk_score": 0}
    
    async def _check_rapid_account_creation(self, device_id: str = None, ip_address: str = None) -> Dict[str, Any]:
        """Check for rapid account creation from same device/IP"""
        try:
            if not self.db or (not device_id and not ip_address):
                return {"rule": FraudRuleType.RAPID_ACCOUNT_CREATION.value, "triggered": False, "risk_score": 0}
            
            # Check for multiple accounts created from same device/IP in last 24 hours
            query = text("""
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE action = 'user_registration'
                AND created_at >= :one_day_ago
                AND (
                    (:device_id IS NOT NULL AND new_values->>'device_id' = :device_id)
                    OR (:ip_address IS NOT NULL AND ip_address = :ip_address)
                )
            """)
            
            one_day_ago = datetime.utcnow() - timedelta(days=1)
            result = await self.db.execute(
                query,
                {
                    "one_day_ago": one_day_ago,
                    "device_id": device_id,
                    "ip_address": ip_address
                }
            )
            row = result.fetchone()
            account_count = row[0] if row else 0
            
            triggered = account_count > 3
            
            return {
                "rule": FraudRuleType.RAPID_ACCOUNT_CREATION.value,
                "triggered": triggered,
                "risk_score": 35 if triggered else 0,
                "details": {
                    "accounts_created_24h": account_count,
                    "threshold": 3,
                    "device_id": device_id,
                    "ip_address": ip_address
                }
            }
        
        except Exception as e:
            logger.error(f"Error checking rapid account creation: {e}")
            return {"rule": FraudRuleType.RAPID_ACCOUNT_CREATION.value, "triggered": False, "risk_score": 0}
    
    def _get_recommendation(self, risk_score: int) -> str:
        """Get recommendation based on risk score"""
        if risk_score >= self.risk_threshold_suspend:
            return "suspend"
        elif risk_score >= self.risk_threshold_review:
            return "review"
        else:
            return "approve"


# Global fraud detection service instance
fraud_detection_service = FraudDetectionService()
