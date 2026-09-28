ALTER TABLE public.properties
    ADD COLUMN IF NOT EXISTS bathrooms NUMERIC(3, 1) NOT NULL DEFAULT 1.0
    CONSTRAINT ck_properties_bathrooms_nonnegative CHECK (bathrooms >= 0);
