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

        foreach ($this->xml->Property as $property) {

            foreach ($property->File as $file) {
                $floorplan_id = (int) $file['id'];
                $floorplan_images[$floorplan_id] = (string) $file->Src;
            }
        }

        /*
         * Getting floor plans:
         */
        foreach ($this->xml->Property as $property) {

            foreach ($property->Floorplan as $floorplan) {

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
        }

        /*
         * Getting units:
         */
        foreach ($this->xml->Property as $property) {

            foreach ($property->ILS_Unit as $unit) {

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
                $floorplan_id           = (int) $unit->FloorplanID;
                $floor_plan_image_url   = $floorplan_images[$floorplan_id];
                $price                  = (int) $unit->UnitRent;

                $this->units[$unit_ext_id] = [
                    "floorplan_id"          => $floorplan_id,
                    "unit_id"               => $unit_id,
                    "unit_ext_id"           => $unit_ext_id,
                    "bedrooms_count"        => $bedrooms_count,
                    "bathroom_type"         => $bathroom_type,
                    "bedroom_type"          => $this->floorplans[$floorplan_id]['bedroom_type'],
                    "floor_number"          => $floor_number,
                    "unit_number"           => $this->floorplans[$floorplan_id]['unit_number'],
                    "price"                 => $price,
                    "square_feet"           => $square_feet,
                    "floor_plan_image_url"  => $floor_plan_image_url,
                    "apply_online_url"      => (string) $unit->ApplyOnlineURL,
                    "amenity_list"          => (string) $unit->UnitAmenityList,
                    "floorplan_description" => $this->floorplans[$floorplan_id]['description'],
                    "availability_status"   => (string) $unit->UnitLeasedStatusDescription,
                    "date_available"        => (string) $unit->DateAvailable,
                ];
            }
        }

        /*
         * Apply filters:
         */
        $this->applyFilters();

        /*
         * Grouping:
         */
        foreach($this->units as $unit_id => $unit) {
            $floorplan_id = $unit['floorplan_id'];
            $this->units_grouped[$floorplan_id][$unit_id] = $unit;
        }
    }

    public function getFloorplans()
    {
        $floorplans = [];

        foreach ($this->units_grouped as $floorplan_id => $units) {
            $floorplans[$floorplan_id] = $this->floorplans[$floorplan_id];

            $available_floors = [];
            foreach ($units as $unit) {
                if (!in_array($unit['floor_number'], $available_floors)) {
                    $available_floors[] = $unit['floor_number'];
                }
            }

            $floorplans[$floorplan_id]['floors'] = implode(',', $available_floors);
        }

        return $floorplans;
    }

    private function applyFilters()
    {
        /*
         * Available/Unoccupied units:
         */
        foreach ($this->units as $id => $unit) {
            if (!in_array($unit['availability_status'], [
                'Vacant Unrented Ready',
                'Vacant Unrented Not Ready'])) {
                unset($this->units[$id]);
            }
        }

        /*
         * 120 days constraint:
         */
        $date_constraint = time() + 10368000;
        foreach ($this->units as $id => $unit) {
            if (strtotime($unit['date_available']) > $date_constraint) {
                unset($this->units[$id]);
            }
        }

        /*
         * 10 Units Per Floor Plan ID:
         */
        $unit_groups = [];

        foreach ($this->units as $unit) {
            $floorplan_id = $unit['floorplan_id'];
            $unit_groups[$floorplan_id][] = $unit;
        }

        foreach ($unit_groups as $group) {

            if (count($group) <= 10) {
                continue;
            }

            /*
             * Sorting:
             */
            uasort($group, function($a, $b) {
                if ((float)$a['price'] == (float)$b['price']) {
                    return 0;
                }
                return ((float)$a['price'] < (float)$b['price']) ? -1 : 1;
            });

            $removed_units = array_splice($group, 10);

            foreach($removed_units as $removed_unit) {
                $unit_ext_id = $removed_unit['unit_ext_id'];
                unset($this->units[$unit_ext_id]);
            }
        }
    }
}
