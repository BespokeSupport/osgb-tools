<?php

namespace Academe\OsgbTools;

/**
 * The model for an Ordnace Survey (OS) Great Britain (GB) National Grid Reference (NGR).
 * The Irish grid is handled slightly differently.
 *
 * The model represents the bottom-left corner (South-West) of a square in the OSGB NGR.
 * A square ranges in size from 1m to 500km, or even bigger without letters.
 *
 * The approach being taken is to make all values relative to square VV (far SW limit)
 * and convert to more appropriate offsets as needed for calculations and I/O. This may
 * be the right approach, or may be wrong, but we'll go this route and see what happens.
 *
 * This model does not perform conversions to other geographic coordinate systems; it is
 * just for bringing together the various different formats used in OSGB into one class
 * for eash of use.
 *
 * TODO: parse a coordinate string in any format.
 * TODO: output a coordinate string in any format (some kind of template, perhaps).
 * TODO: parse format details so the output format can be defaulted to the input format. This effectively sets the square size.
 * TODO: provide methods to handle determining if a square is in a valid range.
 * TODO: work out how the Irish grid can be implemented through shared code.
 */

class Square
{
    /**
     * The national grid reference type (OSGB or Irish)
     */

    const NGR_TYPE = 'OSGB';

    /**
     * The easting value.
     * Integer 0 to 9999999.
     * Represents the number of metres East from the Western-most edge
     * of square VV.
     */

    protected $abs_easting;

    /**
     * The northing value.
     * Integer 0 to 9999999.
     * Represents the number of metres North from the Southern-most edge
     * of a square VV.
     */

    protected $abs_northing;

    /**
     * The default number of letters to use in output formatting.
     */

    protected $number_of_letters = 2;

    /**
     * The default number of digits to use for the easting and northing in output formatting.
     */

    protected $number_of_digits = 5;

    /**
     * The maximum number of digits (for easting or nothing).
     * This is the maximum number with no letters. Each letter will
     * reduce this by one.
     */

    const MAX_DIGITS = 7;

    /**
     * The maximum number of letters in a coordinate.
     */

    const MAX_LETTERS = 2;

    /**
     * The number of metres East of square VV where the Western-most 500km square
     * of GB (square S) is located.
     * 1000km
     */

    const ORIGIN_EAST = 1000000;

    /**
     * The number of metres North of square VV where the Southern-most 500km square
     * of GB (square S) is located.
     * 500km
     */

    const ORIGIN_NORTH = 500000;

    /**
     * The letters used to name squares, in a 5x5 grid.
     * 'V' is the bottom left (South-West).
     */

    const LETTERS = 'VWXYZQRSTULMNOPFGHJKABCDE';

    /**
     * Square sizes, in metres.
     */

    const KM500 = 500000;
    const KM100 = 100000;

    /**
     * Valid squares, used by the OS.
     * These cover just land in GB.
     * There is no reason in theory why sqaures outside these bounds cannot be
     * used, apart from being very inaccurate, so validation against these letter
     * combinations will be optional.
     */

    protected $valid_squares = array(
        'H' => array(
            'HP',
            'HT',
            'HU',
            'HW',
            'HX',
            'HY',
            'HZ',
        ),
        'N' => array(
            'NA',
            'NB',
            'NC',
            'ND',
            'NF',
            'NG',
            'NH',
            'NJ',
            'NK',
            'NL',
            'NM',
            'NM',
            'NO',
            'NR',
            'NS',
            'NT',
            'NU',
            'NW',
            'NX',
            'NY',
            'NZ',
        ),
        'O' => array(
            'OV',
        ),
        'S' => array(
            'SC',
            'SD',
            'SE',
            'SH',
            'SJ',
            'SK',
            'SM',
            'SN',
            'SO',
            'SP',
            'SR',
            'SS',
            'ST',
            'SU',
            'SV',
            'SW',
            'SX',
            'SY',
            'SZ',
        ),
        'T' => array(
            'TA',
            'TF',
            'TG',
            'TL',
            'TM',
            'TQ',
            'TR',
            'TV',
        ),
    );

    /**
     * Convert a letter to its Eastern zero-based postion in a 25x25 grid
     * TODO: validation check on letter.
     */

    public static function letterEastPosition($letter)
    {
        return (strpos(static::LETTERS, strtoupper($letter)) % 5);
    }

    /**
     * Convert a letter to its North zero-based postion in a 25x25 grid
     */

    public static function letterNorthPosition($letter)
    {
        return floor(strpos(static::LETTERS, strtoupper($letter)) / 5);
    }

    /**
     * Convert one or two letters to number of metres East of square VV.
     * TODO: validate letters string.
     */

    public static function lettersToAbsEast($letters)
    {
        // If there are no letters, then default to square 'S'.
        if (empty($letters)) {
            $letters = 'S';
        }

        // Split the string into an array of single letters.
        $split = str_split($letters);

        // The first letter will aways be the 500km square.
        $east = static::KM500 * static::letterEastPosition($split[0]);

        // The optional second letter will identify the 100km square.
        if (isset($split[1]) && static::MAX_LETTERS == 2) {
            $east += static::KM100 * static::letterEastPosition($split[1]);
        }

        return $east;
    }

    /**
     * Convert one or two letters to number of metres North of square VV.
     * TODO: validate letters string.
     */

    public static function lettersToAbsNorth($letters)
    {
        // If there are no letters, then default to square 'S'.
        // Without letters, this is assumed to be the origin.
        if (empty($letters)) {
            $letters = 'S';
        }

        // Split the string into an array of single letters.
        $split = str_split($letters);

        // The first letter will aways be the 500km square.
        $north = static::KM500 * static::letterNorthPosition($split[0]);

        // The optional second letter will identify the 100km square.
        if (isset($split[1]) && static::MAX_LETTERS == 2) {
            $north += static::KM100 * static::letterNorthPosition($split[1]);
        }

        return $north;
    }

    /**
     * Convert a numeric string of N digits to a North or East offset value, in metres.
     * e.g. "NE 01230 14500" will be at 1230m East of the West edge of 100km sqaure "NE".
     *
     * The digits will represent an offset in a box 10km, 100km
     * or 1000km. The size of the box will depend on how many letters are used with the
     * representation of the position.
     * Leading zeroes are significant, and the digits are right-padded with zeroes to fill
     * one of the three box sizes, then converted to an integer, in metres.
     * The box size is in km, and can be 10, 100 or 1000.
     * The default is a 10km box, with up to 5 digits identifying a 1m location, with the
     * most sigificant digit (which may be a zero) identifying a 10km box.
     *
     * Alternatively, pass in the number of letters available in place of the box
     * size (0, 1 or 2).
     *
     * TODO: validation.
     * CHECKME: does truncating to the right make sense when the string is too long?
     */

    public static function digitsToDistance($digits, $number_of_letters = 2)
    {
        switch ($number_of_letters) {
            case 0:
                $pad_size = static::MAX_DIGITS;
                break;

            case 1:
                $pad_size = static::MAX_DIGITS - 1;
                break;

            default:
            case 2:
                $pad_size = static::MAX_DIGITS - min(2, static::MAX_LETTERS);
                break;
        }

        // Pad the string out, or truncate if it started too long.
        $padded = substr(str_pad($digits, $pad_size, '0', STR_PAD_RIGHT), 0, $pad_size);

        // Now return as an integer number of metres.
        return (int)$padded;
    }

    /**
     * Return the absolute east offset for letters and a number string.
     * There can be zero, one or two letters.
     */

    public static function toAbsEast($letters, $digits)
    {
        $east = static::lettersToAbsEast($letters);

        $east += static::digitsToDistance($digits, strlen($letters));

        return $east;
    }

    /**
     * Return the absolute north offset for letters and a number string.
     * There can be zero, one or two letters.
     */

    public static function toAbsNorth($letters, $digits)
    {
        $north = static::lettersToAbsNorth($letters);

        $north += static::digitsToDistance($digits, strlen($letters));

        return $north;
    }

    /**
     * Convert an ABS East/North value pair into letters.
     * The number of letters is 0, 1 or 2.
     */

    public static function absToLetters($abs_east, $abs_north, $number_of_letters)
    {
        // TODO: if no letters are needed, then we are dealing with seven-digit numbers
        // based on the square SV origin. Make sure the ABS east and north values are
        // positive wrt square SV. This system is designed to avoid handling negative numbers
        // in all situations.

        $letters = array();

        if ($number_of_letters >= 1) {
            // Get the first letter (we have at least one).
            // Find the offset on the 5x5 500km grid.
            $east_500_offset = floor($abs_east / static::KM500);
            $north_500_offset = floor($abs_north / static::KM500);

            $letters[] = substr(static::LETTERS, ($north_500_offset * 5) + $east_500_offset, 1);
        }

        if ($number_of_letters >= 2 && $number_of_letters <= static::MAX_LETTERS) {
            // Get the second letter (if the grid system supports it).

            // Find the offset on the 5x5 100km grid, within the 500km grid.
            $east_100_offset = floor(($abs_east - static::KM500 * $east_500_offset) / static::KM100);
            $north_100_offset = floor(($abs_north - static::KM500 * $north_500_offset) / static::KM100);

            $letters[] = substr(static::LETTERS, ($north_100_offset * 5) + $east_100_offset, 1);
        }

        return implode('', $letters);
    }

    /**
     * Convert an ABS East value into digits.
     * The number of letters we are using with the digits is 0, 1 or 2.
     * The number of digits is between 0 and MAX_DIGITS, but the number of digits and
     * the number of letters combined must not be more than MAX_DIGITS.
     * If the number of letters is zero, then the assumed letter origin will
     * be 500km square 'S'.
     */

    public static function absEastToDigits($abs_east, $number_of_letters, $number_of_digits)
    {
        switch ($number_of_letters) {
            case 0:
                // No letters, so an actual number of metres East square S.
                $offset = $abs_east - static::ORIGIN_EAST;
                break;

            case 1:
                // One letter, so an offset within the 500km box.
                $offset = $abs_east % static::KM500;
                break;

            case 2:
                // Two letters, so an offset within a 100km box.
                $offset = $abs_east % static::KM100;
                break;
        }

        // Knock some digits off if it comes to greater than MAX_DIGITS, when counting the letters too.
        if ($number_of_letters + $number_of_digits > static::MAX_DIGITS) {
            $number_of_digits = static::MAX_DIGITS - $number_of_letters;
        }

        // Left-pad the number to 5, 6, or 7 digits, depending on the number of letters.
        $digits = str_pad((string)$offset, static::MAX_DIGITS - $number_of_letters, '0', STR_PAD_LEFT);

        // Now take only the required significant digits.
        return substr($digits, 0, $number_of_digits);
    }

    public static function absNorthToDigits($abs_north, $number_of_letters, $number_of_digits)
    {
        switch ($number_of_letters) {
            case 0:
                // No letters, so an actual number of metres East square S.
                $offset = $abs_north - static::ORIGIN_NORTH;
                break;

            case 1:
                // One letter, so an offset within the 500km box.
                $offset = $abs_north % static::KM500;
                break;

            case 2:
                // Two letters, so an offset within a 100km box.
                $offset = $abs_north % static::KM100;
                break;
        }

        // Knock some digits off if it comes to greater than MAX_DIGITS, when counting the letters too.
        if ($number_of_letters + $number_of_digits > static::MAX_DIGITS) {
            $number_of_digits = static::MAX_DIGITS - $number_of_letters;
        }

        // Left-pad the number to 5, 6, or 7 digits, depending on the number of letters.
        $digits = str_pad((string)$offset, static::MAX_DIGITS - $number_of_letters, '0', STR_PAD_LEFT);

        // Now take only the required significant digits.
        return substr($digits, 0, $number_of_digits);
    }

    /**
     * Set the number of letters to be used by default for output.
     *
     * If we are changing the number of letters, then adjust the number of digits
     * to keep the same accuracy, i.e. the same box size.
     *
     * TODO: validation (integer)
     */

    public function setNumberOfLetters($number_of_letters)
    {
        // Pull to within the bounds.
        if ($number_of_letters < 0) $number_of_letters = 0;
        if ($number_of_letters > static::MAX_LETTERS) $number_of_letters = static::MAX_LETTERS;

        // The number of letters we are increasing the current format by.
        $letter_increase = $number_of_letters - $this->number_of_letters;

        if ($letter_increase != 0) {
            // Decrease the current number of digits by the same amount.
            $new_number_of_digits = $this->getNumberOfDigits() - $letter_increase;

            // Set the new number of digits.
            // Overflow is handled in here.
            $this->setNumberOfDigits($new_number_of_digits);
        }

        $this->number_of_letters = $number_of_letters;

        return $this;
    }

    /**
     * Get the number of letters to be used by deafult for output.
     */

    public function getNumberOfLetters()
    {
        return $this->number_of_letters;
    }

    /**
     * Set the number of digits to be used by default for output.
     *
     * TODO: validation (0 to MAX_DIGITS)
     */

    public function setNumberOfDigits($number_of_digits)
    {
        // Pull the values into the allowed bounds.
        if ($number_of_digits < 0) $number_of_digits = 0;
        if ($number_of_digits > static::MAX_DIGITS) $number_of_digits = static::MAX_DIGITS;

        $this->number_of_digits = $number_of_digits;

        return $this;
    }

    /**
     * Get the number of digits to be used by default for output.
     */

    public function getNumberOfDigits()
    {
        return $this->number_of_digits;
    }

    /**
     * Set the value of the square.
     * The letters, easting and northinng strings, are stored as absolute
     * offsets from square VV, so all information about the original format
     * and consequently the square size it represents, is lost. What is
     * retained is the position to one metre.
     *
     * TODO: validation
     */

    public function setParts($letters, $easting, $northing)
    {
        // Set the default number of letters to be used for output formatting.
        $this->setNumberOfLetters(strlen($letters));

        $this->abs_easting = static::toAbsEast($letters, $easting);
        $this->abs_northing = static::toAbsNorth($letters, $northing);

        return $this;
    }

    /**
     * Get the letters for the current value.
     * The number of letters can be overwridden.
     */

    public function getLetters($number_of_letters = null)
    {
        if ( ! isset($number_of_letters)) {
            $number_of_letters = $this->getNumberOfLetters();
        }

        return $this->absToLetters($this->abs_easting, $this->abs_northing, $number_of_letters);
    }

    /**
     * Get the current easting.
     *
     * TODO: validation
     */

    public function getEasting($number_of_letters = null, $number_of_digits = null)
    {
        if ( ! isset($number_of_letters)) {
            $number_of_letters = $this->getNumberOfLetters();
        }

        if ( ! isset($number_of_digits)) {
            $number_of_digits = $this->getNumberOfDigits();
        }

        return $this->absEastToDigits($this->abs_easting, $number_of_letters, $number_of_digits);
    }

    /**
     * Get the current northinh.
     *
     * TODO: validation
     */

    public function getNorthing($number_of_letters = null, $number_of_digits = null)
    {
        if ( ! isset($number_of_letters)) {
            $number_of_letters = $this->getNumberOfLetters();
        }

        if ( ! isset($number_of_digits)) {
            $number_of_digits = $this->getNumberOfDigits();
        }

        return $this->absNorthToDigits($this->abs_northing, $number_of_letters, $number_of_digits);
    }

    /**
     * Get the current square size, i.e. the accuracy of the geographic reference.
     * The square size will vary between 1m (two letters and five digits, OSGB) and
     * 500km (just a single OSGB letter).
     * A single digit, and no letter, in has a square size of 1000km.
     */

    public function getSize($number_of_letters = null, $number_of_digits = null)
    {
        if ( ! isset($number_of_letters)) {
            $number_of_letters = $this->getNumberOfLetters();
        }

        if ( ! isset($number_of_digits)) {
            $number_of_digits = $this->getNumberOfDigits();
        }

        $total_characters = $number_of_letters + $number_of_digits;

        if ($total_characters > static::MAX_DIGITS) {
            $total_characters = static::MAX_DIGITS;
        }

        // Each missing character from MAX_DIGITS takes the accuracy down by a factor
        // of ten. The last remaining letter has a square size of 500km, not 1000km.
        $missing_factor = static::MAX_DIGITS - $total_characters;

        // Are we down to one letter only?
        if ($total_characters == 1 && $number_of_letters == 1) {
            // A single letter has a aquare size of 500km.
            $final_multiplier = 5;

            // Knock the missing accuracy factor down by one, as this is what the
            // multiplier will replace.
            $missing_factor -= 1;
        } else {
            // Any other combination of letters and digits will be handled as a factor of ten.
            $final_multiplier = 1;
        }

        // The square size is ten to the power of the number of digits less than the
        // maximum, with an exception (final multiplier) for a single letter. Location
        // 'S' of OSGB will be the South West region of Great Britain.
        return pow(10, $missing_factor) * $final_multiplier;
    }

    /**
     * Set the value of the square from a single string.
     * Multiple formats are supported, but all take the order:
     *  [letters] [easting northing]
     * - All whitespace and separating characters are disregarded.
     * - Letters and easting/norhting are optional.
     * - Easting and northing must use the same number of digits.
     * - Letters are case-insenstive.
     * - Exceptions will be raised for invalid formats (e.g. invalid
     *   characters, letters in the wrong place, too many letters or
     *   digits, unbalanced easting/northing digit length).
     */

    public function set($ngr)
    {
        // ngr must be a string.
        if ( ! is_string($ngr)) {
            throw new \InvalidArgumentException(
                sprintf('National Grid Reference (NGR) must be a string; %s passed in', gettype($ngr))
            );
        }

        // Get letters upper-case.
        $ngr = strtoupper($ngr);

        // Remove any non-alphanumeric characters.
        $ngr = preg_replace('/[^A-Z0-9]/', '', $ngr);

        // Letters should be at the start only.
        $letters = '';
        $ngr_array = str_split($ngr);
        while(count($ngr_array) && strpos(static::LETTERS, $ngr_array[0]) !== false) {
            $letters .= array_shift($ngr_array);
        }
        $digits = implode($ngr_array);

        // Exception if the number of letters is greater than allowed.
        if (strlen($letters) > static::MAX_LETTERS) {
            throw new \UnexpectedValueException(
                sprintf('An NGR of type %s cannot have more then %d letters; %d passed in', static::NGR_TYPE, static::MAX_LETTERS, strlen($letters))
            );
        }

        // Exception if the digits string contains non-digits.
        if ( ! preg_match('/^[0-9]*$/', $digits)) {
            throw new \UnexpectedValueException(
                sprintf('Invalid (no-numeric) characters found in Easting or Northing digits')
            );
        }

        // Exception if the digits are not balanced.
        if (strlen($digits) % 2 != 0) {
            throw new \UnexpectedValueException(
                sprintf('Eastings and Northings must contain the same number of digits; a combined total of %d digits found', strlen($digits))
            );
        }

        $digit_length = strlen($digits) / 2;

        // Exception if too many digits.
        if (strlen($letters) + $digit_length > static::MAX_DIGITS) {
            throw new \UnexpectedValueException(
                sprintf('Too many digits; a maximum of %d digits for a Easting or Northing is allowed with %d letters; %d digits supplied', static::MAX_DIGITS - strlen($letters), strlen($letters), $digit_length)
            );
        }

        // Split the digits into eastings and northings.
        $eastings = substr($digits, 0, $digit_length);
        $northings = substr($digits, $digit_length);

        $this->setParts($letters, $eastings, $northings);

        $this->setNumberOfDigits($digit_length);

        return $this;
    }

    /**
     * Pass an optional NGR string in at instantiation.
     */

    public function __construct($ngr = '')
    {
        if ($ngr != '') {
            $this->set($ngr);
        }
    }
}

