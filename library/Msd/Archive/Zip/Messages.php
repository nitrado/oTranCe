<?php
/**
 * This file is part of MySQLDumper released under the GNU/GPL 2 license
 * http://www.mysqldumper.net
 *
 * @package         MySQLDumper
 * @subpackage      Archive_Zip
 * @version         SVN: $Rev$
 * @author          $Author$
 */
/**
 * Zip messages class
 *
 * @package         MySQLDumper
 * @subpackage      Archive_Zip
 */
class Msd_Archive_Zip_Messages
{
    private static $_errorMessages = array(
        ZIPARCHIVE::ER_EXISTS => 'The zip archive does already exists.',
        ZIPARCHIVE::ER_INCONS => 'The zip archive is inconsistent.',
        ZIPARCHIVE::ER_INVAL  => 'Invalid argument supplied.',
        ZIPARCHIVE::ER_MEMORY => 'Can not allocate required memory.',
        ZIPARCHIVE::ER_NOENT  => 'File doesn\'t exists in zip archive.',
        ZIPARCHIVE::ER_NOZIP  => 'The given file isn\'t a zip archive.',
        ZIPARCHIVE::ER_OPEN   => 'Can\'t open the zip archive.',
        ZIPARCHIVE::ER_READ   => 'Error occurred while reading from the archive.',
        ZIPARCHIVE::ER_SEEK   => 'Error occurred while seeking inside the archive.',
    );

    /**
     * Returns the mmessage for a ZIP error.
     *
     * @static
     *
     * @param int $zipError
     *
     * @return string
     */
    public static function getErrorMessage($zipError)
    {
        if (isset(self::$_errorMessages[$zipError])) {
            return self::$_errorMessages[$zipError];
        }

        return 'ERROR: Unknown ZipArchive error code';
    }
}
