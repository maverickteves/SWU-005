DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.properties'::regclass AND conname = 'ck_properties_price_positive') THEN
        ALTER TABLE public.properties ADD CONSTRAINT ck_properties_price_positive CHECK (price > 0) NOT VALID;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.properties'::regclass AND conname = 'ck_properties_bedrooms_nonnegative') THEN
        ALTER TABLE public.properties ADD CONSTRAINT ck_properties_bedrooms_nonnegative CHECK (bedrooms >= 0) NOT VALID;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.properties'::regclass AND conname = 'ck_properties_bathrooms_nonnegative') THEN
        ALTER TABLE public.properties ADD CONSTRAINT ck_properties_bathrooms_nonnegative CHECK (bathrooms >= 0) NOT VALID;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.properties'::regclass AND conname = 'ck_properties_area_positive') THEN
        ALTER TABLE public.properties ADD CONSTRAINT ck_properties_area_positive CHECK (area > 0) NOT VALID;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.properties'::regclass AND conname = 'ck_properties_status_valid') THEN
        ALTER TABLE public.properties ADD CONSTRAINT ck_properties_status_valid
            CHECK (status IN ('available', 'pending', 'sold', 'rented', 'under_offer')) NOT VALID;
    END IF;
END $$;
