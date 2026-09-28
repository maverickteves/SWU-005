from sqlalchemy import Column, String, BigInteger, Boolean, Text, DateTime, ForeignKey
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.db.database import Base


class Inquiry(Base):
    __tablename__ = "inquiries"

    id = Column(String(64), primary_key=True, index=True)
    property_id = Column(String(64), ForeignKey("sync_properties.id", ondelete="CASCADE"), nullable=True)
    property_title = Column(String(255), nullable=False)
    property_address = Column(String(255), nullable=False)
    sender_name = Column(String(128), nullable=False)
    sender_email = Column(String(128), nullable=False)
    sender_phone = Column(String(64), nullable=False)
    message = Column(Text, nullable=False)
    time_ago = Column(String(64), default="Just now")
    is_unread = Column(Boolean, default=True)
    updated_at_epoch = Column(BigInteger, nullable=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())

    messages = relationship("ChatMessage", back_populates="inquiry", cascade="all, delete-orphan", order_by="ChatMessage.created_at")


class ChatMessage(Base):
    __tablename__ = "chat_messages"

    id = Column(String(64), primary_key=True, index=True)
    inquiry_id = Column(String(64), ForeignKey("inquiries.id", ondelete="CASCADE"), nullable=False, index=True)
    sender = Column(String(32), nullable=False)
    message_text = Column(Text, nullable=False)
    time_sent = Column(String(64), nullable=False)
    is_from_me = Column(Boolean, default=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())

    inquiry = relationship("Inquiry", back_populates="messages")
