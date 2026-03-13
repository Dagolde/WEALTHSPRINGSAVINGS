"""
Pytest Configuration and Fixtures
"""

import pytest
import os
from fastapi.testclient import TestClient

# Set test environment variables before importing app
os.environ['DATABASE_URL'] = 'postgresql+asyncpg://test:test@localhost:5432/test_db'
os.environ['PAYSTACK_SECRET_KEY'] = 'sk_test_xxxxxxxxxxxxxxxxxxxxx'
os.environ['PAYSTACK_PUBLIC_KEY'] = 'pk_test_xxxxxxxxxxxxxxxxxxxxx'
os.environ['PAYSTACK_WEBHOOK_SECRET'] = 'whsec_xxxxxxxxxxxxxxxxxxxxx'
os.environ['FCM_SERVER_KEY'] = 'test_fcm_server_key'
os.environ['FCM_SENDER_ID'] = 'test_fcm_sender_id'
os.environ['TERMII_API_KEY'] = 'test_termii_api_key'
os.environ['SENDGRID_API_KEY'] = 'SG.test_sendgrid_api_key'
os.environ['LARAVEL_API_KEY'] = 'test_laravel_api_key'
os.environ['SECRET_KEY'] = 'test-secret-key-for-testing-only'

from app.main import app


@pytest.fixture
def client():
    """FastAPI test client"""
    return TestClient(app)


@pytest.fixture
def mock_payment_service():
    """Mock payment service for testing"""
    # TODO: Implement mock payment service
    pass


@pytest.fixture
def mock_notification_service():
    """Mock notification service for testing"""
    # TODO: Implement mock notification service
    pass


@pytest.fixture
def mock_fraud_service():
    """Mock fraud detection service for testing"""
    # TODO: Implement mock fraud detection service
    pass
