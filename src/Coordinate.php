<?php

namespace Academe\OsgbTools;

/**
 * A cut-down version of League/Geotools/Coordinate/Coordinate
 * The ellipsoid is always Airy when dealing with OSGB.
 */

class Coordinate implements CoordinateInterface
{
    /**
     * The latitude of the coordinate.
     *
     * @var double
     */

    protected $latitude;

    /**
     * The longitude of the coordinate.
     *
     * @var double
     */

    protected $longitude;

    /**
     * Set the latitude and the longitude of the coordinates into an selected ellipsoid.
     *
     * @param array|string $coordinates The coordinates.
     *
     * @throws InvalidArgumentException
     */

    public function __construct($coordinates)
    {
        if (is_array($coordinates) && 2 === count($coordinates)) {
            $this->setLatitude($coordinates[0]);
            $this->setLongitude($coordinates[1]);
        } else {
            throw new InvalidArgumentException(
                'It should be a string, an array or a class which implements Geocoder\Result\ResultInterface !'
            );
        }
    }

    /**
     * {@inheritDoc}
     */

    public function normalizeLatitude($latitude)
    {
        return (double) max(-90, min(90, $latitude));
    }

    /**
     * {@inheritDoc}
     */

    public function normalizeLongitude($longitude)
    {
        if (180 === $longitude % 360) {
            return 180.0;
        }

        $mod       = fmod($longitude, 360);
        $longitude = $mod < -180 ? $mod + 360 : ($mod > 180 ? $mod - 360 : $mod);

        return (double) $longitude;
    }

    /**
     * {@inheritDoc}
     */

    public function setLatitude($latitude)
    {
        $this->latitude = $this->normalizeLatitude($latitude);
    }

    /**
     * {@inheritDoc}
     */

    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * {@inheritDoc}
     */

    public function setLongitude($longitude)
    {
        $this->longitude = $this->normalizeLongitude($longitude);
    }

    /**
     * {@inheritDoc}
     */

    public function getLongitude()
    {
        return $this->longitude;
    }
}
