<?php

    // Add the class.
    require_once 'YOUR_PATH_TO/FileUploader.class.php';
    
    // Create a new instance to upload an image which size must be lower than 2MB.
    $file = new FileUploader($_FILES['YOUR_INPUT_NAME'], 'Image', 2);
    
    // First validations.
    if(!$file->hasRightSizeAndType())
        die($file->getErrorMessage());

    // Security validations.
    if(!$file->isSafe())
        die($file->getErrorMessage());

    // Set the file destination. DO NOT ADD THE LAST SLASH or will cause error.
    if(!$file->setDestination('PATH/TO/SAVE/THE/IMAGE'))
        die($file->getErrorMessage());
    
    // Save the image in the selected path (you can also force a filename).
    $path_to_image = $file->save();
    if(empty($path_to_image))
        die($file->getErrorMessage());
    
    // You're done
    echo $path_to_image;

?>