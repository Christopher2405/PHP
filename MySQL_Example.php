<?php

/*  Assume that we are working on localhost with the default configuration (i.e "root", "no password", port 3306),
    our database is named "TestDB" and we just created the next table:

        CREATE TABLE People (
            Id INT PRIMARY KEY AUTO_INCREMENT,
            Name VARCHAR(64) NOT NULL,
            Age INT NOT NULL
        );
    
    Also, assume all that information is already specified in the class as "Default credentials". Then...
*/

    // Add the class file and create a new instance (using default credentials).
    require_once './MySQL.class.php';
    $db = new MySQL();
    
    // Open a new connection
    if(!$db->open())
        die($db->getErrorDescription());
    
    // Add a new record
    $sql = 'INSERT INTO People VALUES(null, ?, ?)';
    $params = array('Christopher', '24');
    if(!$db->execute($sql, $params))
        die($db->getErrorDescription());
    
    // Sanitize a variable
    $new_name = $db->clear('<script>Christopher</script> Bryan');

    // Update a record and show affected rows
    $sql = 'UPDATE People SET Name=?';
    if(!$db->execute($sql, array($new_name)))
        die($db->getErrorDescription());
    echo 'Affected rows: '.$db->affected_rows."<br>";

    // Perform a query without parameters
    $sql = 'SELECT * FROM People';
    $search = $db->query($sql);
    if($search) {
        while($row = mysqli_fetch_assoc($search))
            echo $row['Id'], $row['Name'], $row['Age'], "<br>";
    }

    // Close the connection
    $db->close();
?>