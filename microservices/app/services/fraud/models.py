"""
Fraud Detection Database Models
SQLAlchemy models for fraud detection and flagging
"""

from sqlalchemy import Column, Integer, String, Float, DateTime, JSON, Boolean, Text, Enum as SQLEnum
from sqlalchemy.sql import func
from datetime import datetime
from enum import Enum
from app.database import Base


class FraudRuleType(str, Enum):
    """Fraud detection rule types"""
    MULTIPLE_FAILED_PAYMENTS = "multiple_failed_payments"
    RAPID_ACCOUNT_CREATION = "rapid_account_creation"
    UNUSUAL_WITHDRAWAL = "unusual_withdrawal"
    DUPLICATE_BANK_ACCOUNT = "duplicate_bank_account"
    CONTRIBUTION_ANOMALY = "contribution_anomaly"
    SUSPICIOUS_PATTERN = "suspicious_pattern"


class FraudFlagStatus(str, Enum):
    """Fraud flag status"""
    PENDING = "pending"
    UNDER_REVIEW = "under_review"
    CONFIRMED = "confirmed"
    FALSE_POSITIVE = "false_positive"
    RESOLVED = "resolved"


class FraudFlag(Base):
    """Fraud flag records for admin review"""
    __tablename__ = "fraud_flags"
    
    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, nullable=False, index=True)
    rule_type = Column(SQLEnum(FraudRuleType), nullable=False)
    risk_score = Column(Float, nullable=False)
    status = Column(SQLEnum(FraudFlagStatus), default=FraudFlagStatus.PENDING, nullable=False)
    
    # Activity details
    activity_type = Column(String(100), nullable=False)
    activity_data = Column(JSON, nullable=True)
    
    # Detection details
    triggered_rules = Column(JSON, nullable=True)  # List of rules that triggered
    detection_metadata = Column(JSON, nullable=True)
    
    # Admin review
    reviewed_by = Column(Integer, nullable=True)
    reviewed_at = Column(DateTime, nullable=True)
    review_notes = Column(Text, nullable=True)
    
    # Timestamps
    created_at = Column(DateTime, default=func.now(), nullable=False)
    updated_at = Column(DateTime, default=func.now(), onupdate=func.now(), nullable=False)


class FraudPattern(Base):
    """Fraud pattern tracking for user behavior analysis"""
    __tablename__ = "fraud_patterns"
    
    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, nullable=False, index=True)
    pattern_type = Column(String(100), nullable=False)
    
    # Pattern metrics
    occurrence_count = Column(Integer, default=1, nullable=False)
    first_occurrence = Column(DateTime, default=func.now(), nullable=False)
    last_occurrence = Column(DateTime, default=func.now(), nullable=False)
    
    # Pattern data
    pattern_data = Column(JSON, nullable=True)
    
    # Risk assessment
    risk_level = Column(String(20), nullable=False)  # low, medium, high, critical
    is_active = Column(Boolean, default=True, nullable=False)
    
    # Timestamps
    created_at = Column(DateTime, default=func.now(), nullable=False)
    updated_at = Column(DateTime, default=func.now(), onupdate=func.now(), nullable=False)


class FraudRule(Base):
    """Configurable fraud detection rules"""
    __tablename__ = "fraud_rules"
    
    id = Column(Integer, primary_key=True, index=True)
    rule_type = Column(SQLEnum(FraudRuleType), nullable=False, unique=True)
    rule_name = Column(String(255), nullable=False)
    description = Column(Text, nullable=True)
    
    # Rule configuration
    is_enabled = Column(Boolean, default=True, nullable=False)
    risk_score_weight = Column(Integer, nullable=False)  # Points to add to risk score
    threshold_config = Column(JSON, nullable=True)  # Rule-specific thresholds
    
    # Actions
    auto_flag = Column(Boolean, default=True, nullable=False)
    auto_suspend = Column(Boolean, default=False, nullable=False)
    notify_admin = Column(Boolean, default=True, nullable=False)
    
    # Timestamps
    created_at = Column(DateTime, default=func.now(), nullable=False)
    updated_at = Column(DateTime, default=func.now(), onupdate=func.now(), nullable=False)
