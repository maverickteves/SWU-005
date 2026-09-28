from typing import Optional
from pydantic import BaseModel, ConfigDict


class HealthStats(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    properties: int = 0
    agents: int = 0
    clients: int = 0
    appointments: int = 0
    inquiries: int = 0


class HealthResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    status: str = "CONNECTED"
    database: str = "real_estate"
    provider: Optional[str] = "PostgreSQL"
    serverTime: Optional[str] = None
    postgresVersion: Optional[str] = None
    latencyMs: int = 0
    stats: HealthStats = HealthStats()
    message: Optional[str] = None


class HealthErrorResponse(BaseModel):
    status: str = "ERROR"
    message: str
    error: Optional[str] = None
