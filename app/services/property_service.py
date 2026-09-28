from typing import List, Optional, Dict, Any
from sqlalchemy.orm import Session
from sqlalchemy import desc
from app.models.property import SyncProperty


def _normalize_string_list(val: Any) -> str:
    if isinstance(val, list):
        return ",".join(str(x) for x in val)
    return str(val or "")


def _text(value: Any, field: str, max_length: int, required: bool = False) -> str:
    if value is None:
        value = ""
    if not isinstance(value, str):
        raise ValueError(f"{field} must be a string.")
    value = value.strip()
    if required and not value:
        raise ValueError(f"{field} is required.")
    if len(value) > max_length:
        raise ValueError(f"{field} must be {max_length} characters or fewer.")
    return value


class PropertyService:

    @staticmethod
    def get_all(db: Session) -> List[SyncProperty]:
        return db.query(SyncProperty).order_by(desc(SyncProperty.created_at)).all()

    @staticmethod
    def get_by_id(db: Session, property_id: str) -> Optional[SyncProperty]:
        return db.query(SyncProperty).filter(SyncProperty.id == property_id).first()

    @staticmethod
    def upsert(db: Session, data: Dict[str, Any], commit: bool = True) -> SyncProperty:
        if not isinstance(data, dict) or not data.get("id"):
            raise ValueError("Each synchronized property must contain a non-empty id.")
        prop_id = str(data["id"]).strip()
        if not prop_id or prop_id.lower() == "none":
            raise ValueError("Each synchronized property must contain a valid id.")
        existing = db.query(SyncProperty).filter(SyncProperty.id == prop_id).first()

        title = _text(data.get("title", ""), "title", 255, required=True)
        address = _text(data.get("address", ""), "address", 255, required=True)
        city_state_zip = _text(data.get("cityStateZip") or data.get("city_state_zip", ""), "cityStateZip", 128)
        price = int(data.get("price", 0))
        if price < 0:
            raise ValueError("price must be zero or greater.")
        price_formatted = data.get("priceFormatted") or data.get("price_formatted", f"₱{price:,}")
        is_rental = bool(data.get("isRental") if "isRental" in data else data.get("is_rental", False))
        beds = float(data.get("beds", 3.0))
        baths = float(data.get("baths", 2.0))
        sqft = int(data.get("sqft", 1500))
        if beds < 0 or baths < 0 or sqft <= 0:
            raise ValueError("beds and baths must be non-negative and sqft must be positive.")
        property_type = _text(data.get("propertyType") or data.get("property_type", "House"), "propertyType", 64, required=True)
        description = _text(data.get("description", ""), "description", 10000)
        image_res_id = int(data.get("imageResId") or data.get("image_res_id", 0))
        media_uris = _normalize_string_list(data.get("mediaUris") or data.get("media_uris", ""))
        is_favorite = bool(data.get("isFavorite") if "isFavorite" in data else data.get("is_favorite", False))
        status = _text(data.get("status", "Active"), "status", 64, required=True)
        available_dates = _normalize_string_list(data.get("availableDates") or data.get("available_dates", ""))
        available_time_slots = _normalize_string_list(data.get("availableTimeSlots") or data.get("available_time_slots", ""))
        amenities = _normalize_string_list(data.get("amenities", ""))
        year_built = int(data.get("yearBuilt") or data.get("year_built", 2023))
        agent_name = _text(data.get("agentName") or data.get("agent_name", "Sarah Jenkins"), "agentName", 128)
        agent_title = _text(data.get("agentTitle") or data.get("agent_title", ""), "agentTitle", 255)
        agent_phone = _text(data.get("agentPhone") or data.get("agent_phone", ""), "agentPhone", 64)
        agent_email = _text(data.get("agentEmail") or data.get("agent_email", ""), "agentEmail", 128)

        if existing:
            existing.title = title
            existing.price = price
            existing.price_formatted = price_formatted
            existing.description = description
            existing.is_favorite = is_favorite
            existing.media_uris = media_uris
            existing.status = status
            if commit:
                db.commit()
            else:
                db.flush()
            db.refresh(existing)
            return existing
        else:
            new_prop = SyncProperty(
                id=prop_id,
                title=title,
                address=address,
                city_state_zip=city_state_zip,
                price=price,
                price_formatted=price_formatted,
                is_rental=is_rental,
                beds=beds,
                baths=baths,
                sqft=sqft,
                property_type=property_type,
                description=description,
                image_res_id=image_res_id,
                media_uris=media_uris,
                is_favorite=is_favorite,
                status=status,
                available_dates=available_dates,
                available_time_slots=available_time_slots,
                amenities=amenities,
                year_built=year_built,
                agent_name=agent_name,
                agent_title=agent_title,
                agent_phone=agent_phone,
                agent_email=agent_email
            )
            db.add(new_prop)
            if commit:
                db.commit()
            else:
                db.flush()
            db.refresh(new_prop)
            return new_prop

    @staticmethod
    def delete(db: Session, property_id: str) -> bool:
        prop = db.query(SyncProperty).filter(SyncProperty.id == property_id).first()
        if prop:
            db.delete(prop)
            db.commit()
            return True
        return False
