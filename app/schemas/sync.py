from typing import List

from pydantic import BaseModel, ConfigDict, Field


class SyncRecord(BaseModel):
    """A sync record with a validated identifier and compatible extra fields."""

    model_config = ConfigDict(extra="allow", str_strip_whitespace=True)

    id: str = Field(..., min_length=1, max_length=64)


class SyncPayload(BaseModel):
    """Bulk sync envelope accepted by the mobile synchronization API."""

    model_config = ConfigDict(extra="forbid")

    properties: List[SyncRecord] = Field(default_factory=list, max_length=1000)
    appointments: List[SyncRecord] = Field(default_factory=list, max_length=1000)
    inquiries: List[SyncRecord] = Field(default_factory=list, max_length=1000)
    notifications: List[SyncRecord] = Field(default_factory=list, max_length=1000)


class SyncResult(BaseModel):
    success: bool
    message: str
    syncedAt: str
