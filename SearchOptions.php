<?php

class SearchOptions
{
    private $floorplans;

    public function __construct($floorplans)
    {
        $this->floorplans = $floorplans;

        $this->createBedroomsStructure();
        $this->createFloorsStructure();
        $this->createPricesStructure();
    }

    private function createBedroomsStructure()
    {
        $bedrooms_structure = [];

        foreach ($this->floorplans as $id => $floorplan) {
            $bedrooms_count = $floorplan['bedrooms_count'];
            $bedrooms_structure[$bedrooms_count][$id] = $floorplan;
        }

        /*
         * Add "All" item:
         */
        $bedrooms_structure['*'] = [];
        foreach($bedrooms_structure as $bedroom_type => $floorplan) {
            $bedrooms_structure['*'] += $floorplan;
        }

        $this->floorplans = $bedrooms_structure;
    }

    private function createFloorsStructure()
    {
        $floors_structrue = [];

        foreach ($this->floorplans as $bedroom_type => $floorplans) {
            foreach ($floorplans as $id => $floorplan) {

                $floors = explode(",", $floorplan['floors']);
                foreach ($floors as $floor) {
                    $floors_structrue[$bedroom_type][$floor][$id] = $floorplan;
                }
                $floors_structrue[$bedroom_type]['*'][$id] = $floorplan;
            }
        }

        $this->floorplans = $floors_structrue;
    }

    private function createPricesStructure()
    {
        $prices_structure = [];

        foreach ($this->floorplans as $bedroom_type => $floors) {
            foreach ($floors as $floor => $floorplans) {
                list($min_prices, $max_prices) = $this->getPricesRange($floorplans);
                $prices_structure[$bedroom_type][$floor]['min_prices'] = $min_prices;
                $prices_structure[$bedroom_type][$floor]['max_prices'] = $max_prices;
            }
        }

        $this->floorplans = $prices_structure;
    }

    private function getPricesRange($floorplans)
    {
        $min_prices = [];
        $max_prices = [];

        foreach ($floorplans as $floorplan) {
            $min_prices[] = $floorplan['min_price'];
            $max_prices[] = $floorplan['max_price'];
        }

        sort($min_prices);
        sort($max_prices);

        return [$min_prices, $max_prices];
    }

    public function getStructredList()
    {
        return $this->floorplans;
    }

    public function getJsonStructuredList()
    {
        return json_encode($this->floorplans);
    }
}
