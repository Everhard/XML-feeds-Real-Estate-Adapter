<?php

class Adapter
{
    private $xml;

    private $floorplans;
    private $units;
    private $units_grouped;

    public function __construct($path_to_xml)
    {
        $this->xml = simplexml_load_file($path_to_xml);

        /*
         * Getting floor plan images:
         */
        $floorplan_images = [];

        foreach ($this->xml->Property->File as $file) {
            $floor_plan_id = (int) $file['id'];
            $floorplan_images[$floor_plan_id] = (string) $file->Src;
        }

        /*
         * Getting floor plans:
         */
        foreach ($this->xml->Property->Floorplan as $floorplan) {

            $floorplan_name = (string) $floorplan->Name;
            $floorplan_id   = (string) $floorplan['id'];
            $bedrooms_count = (int) $floorplan->xpath("Room[@type='bedroom']")[0]->Count;
            $bathroom_count = (int) $floorplan->xpath("Room[@type='bathroom']")[0]->Count;
            $square_feet    = (int) $floorplan->SquareFeet['min'];
            $min_price      = (int) $floorplan->MarketRent['min'];
            $max_price      = (int) $floorplan->MarketRent['max'];

            $bedroom_plural = ($bedrooms_count > 1) ? 's' : '';
            $bedroom_type   = ($bedrooms_count == 0) ? "Studio" : "$bedrooms_count bedroom$bedroom_plural";
            $bedroom_type   = preg_match('/den/i', $floorplan_name) ? $bedroom_type . " + DEN" : $bedroom_type;
            $description    = (string) $floorplan->Name . " - $bedrooms_count bedroom, $bathroom_count bathroom";

            list(, $unit_number) = explode("-", $floorplan_name);

            $this->floorplans[$floorplan_id] = [
                "unit_number"           => $unit_number,
                "floor_plan_image_url"  => $floorplan_images[$floorplan_id],
                "bedrooms_count"        => $bedrooms_count,
                "bedroom_type"          => $bedroom_type,
                "square_feet"           => $square_feet,
                "min_price"             => $min_price,
                "max_price"             => $max_price,
                "description"           => $description,
            ];
        }

        /*
         * Getting units:
         */
        foreach ($this->xml->Property->ILS_Unit as $unit) {

            $unit_id                = (string) $unit->UnitID;

            /*
             * Skip non-numeric Unit ID:
             */
            if (!preg_match('/^\d+$/', $unit_id)) {
                continue;
            }

            $unit_ext_id            = (string) $unit->ExtId;
            $unit_id                = (string) $unit->UnitID;
            $square_feet            = number_format((int) $unit->MinSquareFeet);
            $floor_number           = (strlen($unit_id) == 4) ? substr($unit_id, 0, 2) : substr($unit_id, 0, 1);
            $bedrooms_count         = (int) $unit->UnitBedrooms;
            $bathrooms_count        = (int) $unit->UnitBathrooms;
            $bathroom_plural        = ($bathrooms_count > 1) ? 's' : '';
            $bathroom_type          = "$bathrooms_count bathroom$bathroom_plural";
            $floor_plan_id          = (int) $unit->FloorplanID;
            $floor_plan_image_url   = $floorplan_images[$floor_plan_id];
            $price                  = (int) $unit->UnitRent;

            $this->units[$unit_ext_id] = [
                "original"              => $unit,
                "floor_plan_id"         => $floor_plan_id,
                "unit_id"               => $unit_id,
                "bedrooms_count"        => $bedrooms_count,
                "bathroom_type"         => $bathroom_type,
                "bedroom_type"          => $this->floorplans[$floor_plan_id]['bedroom_type'],
                "floor_number"          => $floor_number,
                "unit_number"           => $this->floorplans[$floor_plan_id]['unit_number'],
                "price"                 => $price,
                "square_feet"           => $square_feet,
                "floor_plan_image_url"  => $floor_plan_image_url,
                "apply_online_url"      => (string) $unit->ApplyOnlineURL,
                "amenity_list"          => (string) $unit->UnitAmenityList,
                "floorplan_description" => $this->floorplans[$floor_plan_id]['description'],
            ];
        }

        /*
         * Grouping:
         */
        foreach($this->units as $unit_id => $unit) {
            $floor_plan_id = $unit['floor_plan_id'];
            unset($unit['original']);
            $this->units_grouped[$floor_plan_id][$unit_id] = $unit;
        }
    }
}
