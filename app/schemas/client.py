from datetime import datetime
import re
from typing import Optional
from pydantic import BaseModel, ConfigDict, EmailStr, Field, field_validator, model_validator


class ClientBase(BaseModel):
    model_config = ConfigDict(str_strip_whitespace=True)
    first_name: str = Field(..., min_length=1, max_length=100, description="Client first name")
    last_name: str = Field(..., min_length=1, max_length=100, description="Client last name")
    email: EmailStr = Field(..., description="Unique email address for client")
    phone: Optional[str] = Field(None, max_length=50, description="Phone contact number")

    @field_validator("email")
    @classmethod
    def normalize_email(cls, value: EmailStr) -> str:
        return str(value).strip().lower()

    @field_validator("first_name", "last_name")
    @classmethod
    def validate_name(cls, value: str) -> str:
        if not any(char.isalpha() for char in value):
            raise ValueError("Name must contain at least one letter.")
        return value

    @field_validator("phone")
    @classmethod
    def validate_phone(cls, value: Optional[str]) -> Optional[str]:
        if value is None:
            return value
        if value and (not re.fullmatch(r"[0-9+().\-\s]{7,50}", value) or not any(char.isdigit() for char in value)):
            raise ValueError("Phone number must contain at least 7 characters and one digit.")
        return value or None


class ClientCreate(ClientBase):
    pass


class ClientUpdate(BaseModel):
    model_config = ConfigDict(str_strip_whitespace=True)
    first_name: Optional[str] = Field(None, min_length=1, max_length=100)
    last_name: Optional[str] = Field(None, min_length=1, max_length=100)
    email: Optional[EmailStr] = None
    phone: Optional[str] = Field(None, max_length=50)

    @field_validator("email")
    @classmethod
    def normalize_email(cls, value: Optional[EmailStr]) -> Optional[str]:
        return str(value).strip().lower() if value is not None else None

    @field_validator("first_name", "last_name")
    @classmethod
    def validate_name(cls, value: Optional[str]) -> Optional[str]:
        if value is not None and not any(char.isalpha() for char in value):
            raise ValueError("Name must contain at least one letter.")
        return value

    @field_validator("phone")
    @classmethod
    def validate_phone(cls, value: Optional[str]) -> Optional[str]:
        if value is not None and value and (not re.fullmatch(r"[0-9+().\-\s]{7,50}", value) or not any(char.isdigit() for char in value)):
            raise ValueError("Phone number must contain at least 7 characters and one digit.")
        return value or None

    @model_validator(mode="after")
    def require_update_field(self):
        if not self.model_fields_set:
            raise ValueError("At least one client field must be provided.")
        return self


class ClientResponse(ClientBase):
    model_config = ConfigDict(from_attributes=True)

    id: int
    created_at: datetime
    updated_at: Optional[datetime] = None
