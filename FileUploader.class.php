<?php
/*
*   FILE UPLOADER: A basic PHP class/template to provide a first-step security for your website uploads.
*
*   Author: Christopher Bryan Padilla-Vallejo
*   
*   You are completely free to use, copy, modify and distribute this code for any purposes (commercial or not)
*   as long as this header is left intact.
*/

// bytes in a Megabyte (available if you need to use it outside of this class)
define('MB', 1048576);

class FileUploader
{
    // Common errors
    private $errors = array(
        'EMPTY_OBJECT' => 'The file was not received or is empty.',
        'FILE_TOO_BIG' => 'The file size is greater than the allowed.',
        'FILE_NOT_SAFE' => 'The file is not safe and should not be uploaded.',
        'INVALID_FILE_TYPE' => 'The specified type of file (in constructor) is not in the supported list.',
        'BAD_EXTENSION' => 'This file extension is not allowed.',
        'INVALID_DESTINATION' => 'Destination path was not given, does not exist or is not a directory.',
        'WRITE_NOT_ALLOWED' => 'You don\'t have permissions to write the file in the selected directory.',
        'WRITE_FAILED' => 'Something went wrong while trying to save the file in destination directory.'
    );

    private $received_file = NULL;
    private $satisfies_requirements = false;
    private $is_safe = false;
    private $destination_path = '';

    private $blacklist_extensions = array('php','htm','html','js','sh','css','pl','py');
    private $last_error = '';

    private $supported_file_types = array('Image','Document');
    private $selected_file_type = '';

    // Every extension must be index-mapped with its MIME
    private $images_whitelist = array(
        'Extension' => array('jpg','jpeg','png','gif','ico'),
        'Mime' => array('image/jpeg','image/jpeg','image/png','image/gif','image/x-icon')
    );
    private $documents_whitelist = array(
        'Extension' => array('pdf'),
        'Mime' => array('application/pdf')
    );

    /*  ===# RETRIEVES THE LAST-ERROR DESCRIPTION (FOR DEBUG TIME)
    *   [ Returns ]
    *       + The error description (String)
    */
    public function getErrorMessage()
    {
        return $this->last_error.PHP_EOL;
    }

    /*  ===# CREATES A NEW INSTANCE FOR A GIVEN FILE (yes, 1 file)
    *   [ Parameters ]
    *       + The file to be uploaded ($_FILES)
    *       + An item of $supported_file_types (String)
    *       + (Optional,default=8) Maximum file size allowed in Megabytes (Float)
    *   [ Returns ]
    *       + Instance of this class
    */
    function __construct($object, $supposed_filetype, $max_filesize = 8)
    {
        if(empty($object)) {
            $this->last_error = $this->errors['EMPTY_OBJECT'];
        } else {
            if(!in_array($supposed_filetype, $this->supported_file_types))
                $this->last_error = $this->errors['INVALID_FILE_TYPE'];
            else {
                if(($object['size']/MB) > ($max_filesize*MB))
                    $last_error = $this->errors['FILE_TOO_BIG'];
                else {
                    $this->received_file = $object;
                    $this->selected_file_type = $this->supported_file_types[array_search($supposed_filetype, $this->supported_file_types)];
                    $this->satisfies_requirements = true;
                }
            }
        }
    }

    /*  ===# CHECK IF THE FILE BELONGS TO AN ALLOWED TYPE AND HAS THE RIGHT SIZE
    *   [ Returns ]
    *       + Does my file was accepted? (Boolean)
    */
    public function hasRightSizeAndType()
    {
        return $this->satisfies_requirements===true;
    }

    /*  ===# "THE FIREWALL"
    *   [ Returns ]
    *       + Does the file passed all the tests? (Boolean)
    *
    *   > This function also clears the original file name for secure use
    */
    public function isSafe()
    {
        if(!$this->hasRightSizeAndType())
            return false;
        // Clear the file name
        $filename = strtolower(basename(trim($this->received_file['name'])));
        // Check if contains a blacklisted extension
        foreach($this->blacklist_extensions as $unwanted_extension)
            if(strpos($filename, $unwanted_extension) !== false) {
                $this->last_error = $this->errors['FILE_NOT_SAFE'].' Detected extension: '.$unwanted_extension;
                return false;
            }
        $filter_used = NULL;
        // The reason of this: To select a whitelist and also add custom security processing for every case
        switch($this->selected_file_type) {
            case 'Image':   $filter_used = $this->images_whitelist;   break;
            case 'Document': $filter_used = $this->documents_whitelist;   break;
        }
        // Check extension and mime
        $first_dot_index = strpos($filename, '.') + 1; 
        $last_dot_index = strrpos($filename, '.') + 1;
        $ext = substr($filename, $last_dot_index, (strlen($filename)-$last_dot_index));
        $extIndex = array_search($ext, $filter_used['Extension']);
        if($extIndex === false) {
            $this->last_error = $this->errors['BAD_EXTENSION'];
            return true;
        }
        if(strcmp($this->received_file['type'], $filter_used['Mime'][$extIndex]) !== 0) {
            $this->last_error = $this->errors['FILE_NOT_SAFE'].' File extension and MIME doesn\'t match';
            return true;
        }
        // Creating a clean filename ("FILENAME.SINGLE_EXTENSION")
        $filename = substr($filename, 0, $first_dot_index).$ext;
        // All clear
        $this->received_file['name'] = ucfirst(strtolower($filename));
        $this->is_safe = true;
        return false;
    }

    /*  ===# SET THE DIRECTORY WHERE YOU WANT TO PUT THE UPLOADED FILE
    *   [ Parameters ]
    *       + The path (String)
    *   [ Returns ]
    *       + Can your file be there? (Boolean)
    *
    *   > Notice that this function adds the last slash, keep in mind that in your code.
    */
    public function setDestination($path)
    {
        if(!$this->hasRightSizeAndType())
            return false;
        if(!file_exists($path) || !is_dir($path)) {
            $this->last_error = $this->errors['INVALID_DESTINATION'];
            return false;
        }
        if(!is_writable($path)) {
            $this->last_error = $this->errors['WRITE_NOT_ALLOWED'];
            return false;
        }
        $this->destination_path = $path.'/';
        return true;
    }

    /*  ===# PUT THE UPLOADED FILE IN THE DIRECTORY
    *   [ Parameters ]
    *       + (Optional) Force a filename AND EXTENSION (String)
    *   [ Returns ]
    *       + Full path to the file (String) on success or empty string (String) on failure
    */
    public function save($forced_filename = '')
    {
        if(!$this->hasRightSizeAndType())
            return '';
        if($this->is_safe !== true) {
            $this->last_error = $this->errors['FILE_NOT_SAFE'];
            return '';
        }
        if(empty($this->destination_path)) {
            $this->last_error = $this->errors['INVALID_DESTINATION'];
            return '';
        }
        // Recall that, at this point, $this->received_file['name'] is safe
        $definitive_filename = (!empty($forced_filename)) ? $forced_filename : $this->received_file['name'];
        $definitive_filename = $this->destination_path.$definitive_filename;
        if(!move_uploaded_file($this->received_file['tmp_name'], $definitive_filename)) {
            $this->last_error = $this->errors['WRITE_FAILED'];
            return '';
        }
        return $definitive_filename;
    }

    /* ===# CLEAN IT ALL */
    function __destruct()
    {
        foreach($this as $property)
            $property = null;
    }
}
?>