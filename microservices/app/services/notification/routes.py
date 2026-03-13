"""
Notification Service API Routes
Endpoints for sending notifications
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, EmailStr
from typing import Dict, List, Any, Optional
from app.services.notification.service import notification_dispatcher
import logging

logger = logging.getLogger(__name__)

router = APIRouter()


class PushNotificationRequest(BaseModel):
    """Push notification request"""
    user_id: int
    title: str
    body: str
    data: Optional[Dict[str, Any]] = None
    fcm_token: Optional[str] = None  # FCM device token


class SMSRequest(BaseModel):
    """SMS request"""
    phone: str
    message: str


class EmailRequest(BaseModel):
    """Email request"""
    email: EmailStr
    subject: str
    template: str
    data: Optional[Dict[str, Any]] = None


class MultiChannelRequest(BaseModel):
    """Multi-channel notification request"""
    user_id: int
    phone: str
    email: EmailStr
    title: str
    message: str
    channels: Optional[List[str]] = ['push', 'sms', 'email']
    data: Optional[Dict[str, Any]] = None
    fcm_token: Optional[str] = None  # FCM device token


@router.post("/push")
async def send_push(request: PushNotificationRequest):
    """Send push notification"""
    try:
        result = await notification_dispatcher.send_push_notification(
            user_id=request.user_id,
            title=request.title,
            body=request.body,
            data=request.data,
            fcm_token=request.fcm_token
        )
        
        if result:
            return {"success": True, "message": "Push notification sent"}
        else:
            raise HTTPException(status_code=500, detail="Failed to send push notification")
    
    except Exception as e:
        logger.error(f"Push notification error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/sms")
async def send_sms(request: SMSRequest):
    """Send SMS"""
    try:
        result = await notification_dispatcher.send_sms(
            phone=request.phone,
            message=request.message
        )
        
        if result:
            return {"success": True, "message": "SMS sent"}
        else:
            raise HTTPException(status_code=500, detail="Failed to send SMS")
    
    except Exception as e:
        logger.error(f"SMS error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/email")
async def send_email(request: EmailRequest):
    """Send email"""
    try:
        result = await notification_dispatcher.send_email(
            email=request.email,
            subject=request.subject,
            template=request.template,
            data=request.data
        )
        
        if result:
            return {"success": True, "message": "Email sent"}
        else:
            raise HTTPException(status_code=500, detail="Failed to send email")
    
    except Exception as e:
        logger.error(f"Email error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/send")
async def send_multi_channel(request: MultiChannelRequest):
    """Send notification across multiple channels"""
    try:
        results = await notification_dispatcher.send_multi_channel(
            user_id=request.user_id,
            phone=request.phone,
            email=request.email,
            title=request.title,
            message=request.message,
            channels=request.channels,
            data=request.data,
            fcm_token=request.fcm_token
        )
        
        return {
            "success": True,
            "message": "Multi-channel notification sent",
            "results": results
        }
    
    except Exception as e:
        logger.error(f"Multi-channel notification error: {e}")
        raise HTTPException(status_code=500, detail=str(e))
