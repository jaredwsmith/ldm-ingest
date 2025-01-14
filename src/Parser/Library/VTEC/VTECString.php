<?php

namespace chswx\LDMIngest\Parser\Library\VTEC;

use chswx\LDMIngest\Utils;

/**
 * Class to assist with VTEC string operations.
 * VTECString object properties are externally accessible
 */
class VTECString
{
    /**
     * Raw VTEC string.
     *
     * @var string
     */
    public $raw_vtec;

    /**
     * @var string Product class
     */
    public $product_class;

    /**
     * @var string Action
     */
    public $action;

    /**
     * @var string Issuing office ID
     */
    public $office;

    /**
     * @var string Phenomena
     */
    public $phen;

    /**
     * @var string Significance
     */
    public $sig;

    /**
     * @var int Event Tracking Number
     */
    public $etn;

    /**
     * @var int Event effective time as a UNIX timestamp
     */
    public $start_timestamp;

    /**
     * @var int Event expiration time as a UNIX timestamp
     */
    public $end_timestamp;

    /**
     * @var int Inferred year of the event
     */
    public $year;

    /**
     * Constructor.
     * Take array of VTEC tokens and return properly populated object.
     *
     * @param array $vtec Array containing VTEC tokens.
     */
    public function __construct($vtec)
    {
        if (is_array($vtec)) {
            $this->createObj($vtec);
        } else {
            Utils::log("Doing it wrong: Need to only pass in an array. This will not end well.");
            return null;
        }
    }

    /**
     * Checks if an operational VTEC string.
     *
     * @return boolean
     */
    public function isOperational()
    {
        return $this->product_class === 'O';
    }

    /**
     * Checks if a test VTEC product.
     *
     * @return boolean
     */
    public function isTest()
    {
        return $this->product_class === 'T';
    }

    public function isUpgrade()
    {
        return $this->product_class === 'U';
    }

    /**
     * Returns the action type from the VTEC dictionary.
     *
     * @return string action
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Returns the Event Tracking Number.
     *
     * @return int ETN
     */
    public function getETN()
    {
        return $this->etn;
    }

    public function getPhen()
    {
        return $this->phen;
    }

    public function getSig()
    {
        return $this->sig;
    }

    /**
     * Get phenomena and signficance.
     *
     * @return string
     */
    public function getPhenSig()
    {
        return $this->phen . "." . $this->sig;
    }

    /**
     * Returns the three-character WFO identifier.
     * @return string
     */
    public function getOffice()
    {
        return substr($this->office, 1);
    }

    /**
     * Returns the fully qualified ICAO for the issuing center.
     * @return string The ICAO
     */
    public function getOfficeIcao()
    {
        return $this->office;
    }

    /**
     * Get the year from the VTEC string (best-effort).
     * If there is no year provided in the effective timestamp,
     * get the current UTC year.
     * Potential ideas for later:
     * - Try to get the issue year from the segment for more ironclad info.
     * - Try to use H-VTEC to determine the year of issuance for long-running flood warnings.
     * This same code is in the Alerter.
     * TODO: Abstract to a library
     * @return string|false The year in question
     */
    public function getVtecYear()
    {
        $year = date('Y');

        if ($this->start_timestamp !== 0) {
            // Use the effective timestamp to infer the year,
            // but only if it is the same as the current year
            // If NWS issues a VTEC product valid in the new year during
            // the current year, the ETN will go toward the _current_ year
            $eff_year = date('Y', $this->start_timestamp);
            $curr_year = date('Y');

            $year = ($eff_year > $curr_year) ? $curr_year : $eff_year;
        } else if ($this->end_timestamp !== 0) {
            // If the effective timestamp is zeroed out (CON/EXA),
            // we should still be able to infer the year from expiration.
            // If the current year is greater than the expiration year,
            // then use that year (primarily in archival scenarios).
            // Otherwise, use the current year
            $exp_year = date('Y', $this->end_timestamp);
            if ($year > $exp_year) {
                $year = $exp_year;
            }
        }

        return $year;
    }

    ///
    /// Private functions /////////////////////////////////////////////////
    ///

    /**
     * Parse out the VTEC string into its properties
     */
    private function createObj($vtec_string_array)
    {
        // Save the VTEC string in its entirety
        $this->raw_vtec = $vtec_string_array[0];

        // VTEC product class
        $this->product_class = $vtec_string_array[1];

        // VTEC action
        $this->action = $vtec_string_array[2];

        // VTEC issuing WFO
        $this->office = $vtec_string_array[3];

        // VTEC phenomena
        $this->phen = $vtec_string_array[4];

        // VTEC significance
        $this->sig = $vtec_string_array[5];

        // VTEC event number
        $this->etn = (int)$vtec_string_array[6];

        // Effective time (as UNIX timestamp)
        $this->start_timestamp = $this->vtecToTimestamp($vtec_string_array[7], $vtec_string_array[8]);

        // Expire time (as UNIX timestamp)
        $this->end_timestamp = $this->vtecToTimestamp($vtec_string_array[9], $vtec_string_array[10]);

        // Year of event
        $this->year = $this->getVtecYear();
    }

    /**
     * Converts a VTEC timestamp to a UNIX timestamp (normalized to Z time)
     *
     * @return int UNIX timestamp
     */
    private function vtecToTimestamp($vtec_date, $vtec_time)
    {
        // Don't bother with blank dates
        if ($vtec_date == "000000") {
            $stamp = 0;
        } else {
            // Break out the VTEC datestamp into chunks to reassemble shortly
            $year = substr($vtec_date, 0, 2);
            $month = substr($vtec_date, 2, 2);
            $day = substr($vtec_date, 4, 2);

            // Y2.1K problem (read: not mine unless I live to be 130)
            $stamp = strtotime('20' . $year . '-' . $month . '-' . $day . ' ' . $vtec_time . 'Z');
        }

        return $stamp;
    }
}
