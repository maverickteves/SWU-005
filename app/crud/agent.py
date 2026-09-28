from typing import List, Optional
from sqlalchemy.orm import Session
from app.db.models import Agent
from app.schemas.agent import AgentCreate, AgentUpdate


def get_agent(db: Session, agent_id: int) -> Optional[Agent]:
    """Retrieve a single agent by primary key ID."""
    return db.query(Agent).filter(Agent.id == agent_id).first()


def get_agent_by_email(db: Session, email: str) -> Optional[Agent]:
    """Retrieve an agent by email address."""
    return db.query(Agent).filter(Agent.email == email).first()


def get_agents(db: Session, skip: int = 0, limit: int = 100) -> List[Agent]:
    """Retrieve a list of agents with pagination."""
    return db.query(Agent).offset(skip).limit(limit).all()


def create_agent(db: Session, agent_in: AgentCreate) -> Agent:
    """Create and persist a new agent record."""
    db_agent = Agent(
        first_name=agent_in.first_name,
        last_name=agent_in.last_name,
        email=agent_in.email,
        phone=agent_in.phone
    )
    db.add(db_agent)
    db.commit()
    db.refresh(db_agent)
    return db_agent


def update_agent(db: Session, db_agent: Agent, agent_in: AgentUpdate) -> Agent:
    """Update fields on an existing agent."""
    update_data = agent_in.model_dump(exclude_unset=True)
    for field, value in update_data.items():
        setattr(db_agent, field, value)
    db.commit()
    db.refresh(db_agent)
    return db_agent


def delete_agent(db: Session, db_agent: Agent) -> None:
    """Delete an agent record from the database."""
    db.delete(db_agent)
    db.commit()
