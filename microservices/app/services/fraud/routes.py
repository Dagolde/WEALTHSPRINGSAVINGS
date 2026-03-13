"""
Fraud Detection Service API Routes
Endpoints for fraud detection and analysis
"""

from fastapi import APIRouter, HTTPException, Depends
from sqlalchemy.ext.asyncio import AsyncSession
from pydantic import BaseModel
from typing import Dict, Any
from app.services.fraud.service import FraudDetectionService
from app.database import get_db
import logging

logger = logging.getLogger(__name__)

router = APIRouter()


class UserBehaviorRequest(BaseModel):
    """User behavior analysis request"""
    user_id: int


class PaymentFraudRequest(BaseModel):
    """Payment fraud check request"""
    user_id: int
    amount: float
    payment_method: str
    metadata: Dict[str, Any] = {}


class DuplicateAccountRequest(BaseModel):
    """Duplicate account detection request"""
    email: str
    phone: str
    device_id: str = None
    ip_address: str = None


class FlagActivityRequest(BaseModel):
    """Flag suspicious activity request"""
    user_id: int
    activity_type: str
    details: Dict[str, Any]


class WithdrawalFraudRequest(BaseModel):
    """Withdrawal fraud check request"""
    user_id: int
    amount: float
    bank_account: str


@router.post("/analyze-user")
async def analyze_user(request: UserBehaviorRequest, db: AsyncSession = Depends(get_db)):
    """Analyze user behavior for fraud patterns"""
    try:
        service = FraudDetectionService(db)
        result = await service.analyze_user_behavior(
            user_id=request.user_id
        )
        
        return {
            "success": True,
            "data": result
        }
    
    except Exception as e:
        logger.error(f"User behavior analysis error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/analyze-payment")
async def analyze_payment(request: PaymentFraudRequest, db: AsyncSession = Depends(get_db)):
    """Check payment for fraud indicators"""
    try:
        payment_data = {
            "user_id": request.user_id,
            "amount": request.amount,
            "payment_method": request.payment_method,
            "metadata": request.metadata
        }
        
        service = FraudDetectionService(db)
        result = await service.check_payment_fraud(payment_data)
        
        return {
            "success": True,
            "data": result
        }
    
    except Exception as e:
        logger.error(f"Payment fraud check error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/check-duplicate-accounts")
async def check_duplicates(request: DuplicateAccountRequest, db: AsyncSession = Depends(get_db)):
    """Detect potential duplicate accounts"""
    try:
        user_data = {
            "email": request.email,
            "phone": request.phone,
            "device_id": request.device_id,
            "ip_address": request.ip_address
        }
        
        service = FraudDetectionService(db)
        duplicates = await service.detect_duplicate_accounts(user_data)
        
        return {
            "success": True,
            "data": {
                "duplicates_found": len(duplicates),
                "user_ids": duplicates
            }
        }
    
    except Exception as e:
        logger.error(f"Duplicate account detection error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/flag-activity")
async def flag_activity(request: FlagActivityRequest, db: AsyncSession = Depends(get_db)):
    """Flag suspicious activity for review"""
    try:
        activity = {
            "user_id": request.user_id,
            "type": request.activity_type,
            "details": request.details
        }
        
        service = FraudDetectionService(db)
        result = await service.flag_suspicious_activity(activity)
        
        if result:
            return {
                "success": True,
                "message": "Activity flagged for review"
            }
        else:
            raise HTTPException(status_code=500, detail="Failed to flag activity")
    
    except Exception as e:
        logger.error(f"Activity flagging error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/analyze-withdrawal")
async def analyze_withdrawal(request: WithdrawalFraudRequest, db: AsyncSession = Depends(get_db)):
    """Check withdrawal for fraud indicators"""
    try:
        service = FraudDetectionService(db)
        result = await service.check_withdrawal_fraud(
            user_id=request.user_id,
            amount=request.amount,
            bank_account=request.bank_account
        )
        
        return {
            "success": True,
            "data": result
        }
    
    except Exception as e:
        logger.error(f"Withdrawal fraud check error: {e}")
        raise HTTPException(status_code=500, detail=str(e))
