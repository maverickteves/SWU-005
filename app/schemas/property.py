from datetime import datetime
from decimal import Decimal
from typing import Optional
from pydantic import BaseModel, ConfigDict, Field, field_validator, model_validator
from app.schemas.agent import AgentResponse

VALID_STATUSES = {
    "available", "pending", "sold", "rented", "under_offer"
}


class PropertyBase(BaseModel):
    model_config = ConfigDict(str_strip_whitespace=True)
    title: str = Field(..., min_length=2, max_length=255, description="Property listing title")
    description: Optional[str] = Field(None, max_length=5000, description="Detailed description of the property")
    property_type: str = Field(..., min_length=2, max_length=100, description="e.g. House, Apartment, Condo, Villa, Townhouse")
    price: Decimal = Field(..., gt=0, max_digits=12, decimal_places=2)
    address: str = Field(..., min_length=3, max_length=255, description="Street address")
    city: str = Field(..., min_length=2, max_length=100, description="City location")
    bedrooms: int = Field(default=1, ge=0, description="Number of bedrooms (non-negative integer)")
    bathrooms: Decimal = Field(default=Decimal("1.0"), ge=0, max_digits=3, decimal_places=1)
    area: Decimal = Field(..., gt=0, max_digits=10, decimal_places=2)
    status: str = Field(default="available", max_length=50, description="Listing status")
    agent_id: int = Field(..., gt=0, description="Foreign key referencing a valid Agent ID")

    @field_validator("property_type")
    @classmethod
    def validate_property_type(cls, v: str) -> str:
        if len(v.strip()) < 2:
            raise ValueError("Property type must be at least 2 characters long.")
        return v.strip().title()

    @field_validator("title", "address", "city")
    @classmethod
    def validate_text(cls, value: str) -> str:
        if not value.strip():
            raise ValueError("This field cannot be blank.")
        return value

    @field_validator("status")
    @classmethod
    def validate_status(cls, v: str) -> str:
        cleaned = v.strip().lower()
        if cleaned not in VALID_STATUSES:
            raise ValueError(
                f"Invalid status '{v}'. Allowed values: {', '.join(sorted(VALID_STATUSES))}"
            )
        return cleaned


class PropertyCreate(PropertyBase):
    pass


class PropertyUpdate(BaseModel):
    model_config = ConfigDict(str_strip_whitespace=True)
    title: Optional[str] = Field(None, min_length=2, max_length=255)
    description: Optional[str] = Field(None, max_length=5000)
    property_type: Optional[str] = Field(None, min_length=2, max_length=100)
    price: Optional[Decimal] = Field(None, gt=0, max_digits=12, decimal_places=2)
    address: Optional[str] = Field(None, min_length=3, max_length=255)
    city: Optional[str] = Field(None, min_length=2, max_length=100)
    bedrooms: Optional[int] = Field(None, ge=0)
    bathrooms: Optional[Decimal] = Field(None, ge=0, max_digits=3, decimal_places=1)
    area: Optional[Decimal] = Field(None, gt=0, max_digits=10, decimal_places=2)
    status: Optional[str] = Field(None, max_length=50)
    agent_id: Optional[int] = Field(None, gt=0)

    @field_validator("property_type")
    @classmethod
    def validate_property_type(cls, v: Optional[str]) -> Optional[str]:
        if v is not None:
            cleaned = v.strip().lower()
            if len(cleaned) < 2:
                raise ValueError("Property type must be at least 2 characters long.")
            return v.strip().title()
        return v

    @field_validator("status")
    @classmethod
    def validate_status(cls, v: Optional[str]) -> Optional[str]:
        if v is not None:
            cleaned = v.strip().lower()
            if cleaned not in VALID_STATUSES:
                raise ValueError(
                    f"Invalid status '{v}'. Allowed values: {', '.join(sorted(VALID_STATUSES))}"
                )
            return cleaned
        return v

    @field_validator("title", "address", "city")
    @classmethod
    def validate_text(cls, value: Optional[str]) -> Optional[str]:
        if value is not None and not value.strip():
            raise ValueError("This field cannot be blank.")
        return value

    @model_validator(mode="after")
    def require_update_field(self):
        if not self.model_fields_set:
            raise ValueError("At least one property field must be provided.")
        non_nullable = {
            "title", "property_type", "price", "address", "city", "bedrooms",
            "bathrooms", "area", "status", "agent_id",
        }
        if any(getattr(self, name) is None for name in self.model_fields_set & non_nullable):
            raise ValueError("Required property fields cannot be null.")
        return self


class PropertyResponse(PropertyBase):
    model_config = ConfigDict(from_attributes=True)

    id: int
    created_at: datetime
    updated_at: Optional[datetime] = None
    agent: Optional[AgentResponse] = None
