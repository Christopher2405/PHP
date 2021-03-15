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
    require_once './MySQL.php';
    $db = new MySQL();
    
    // Open a new connection
    if(!$db->open())
        die($db->errorMessage());
    
    // Add a new record
    $sql = 'INSERT INTO People VALUES(null, ?, ?)';
    $params = array('Christopher', '24');
    if(!$db->execute($sql, $params))
        die($db->errorMessage());
    
    // Sanitize a variable
    $new_name = $db->clear('<script>Christopher</script> Bryan');

    // Update a record and show affected rows
    $sql = 'UPDATE People SET Name=?';
    $affected_rows = $db->execute($sql, array($new_name));
    if($affected_rows == -1)
        die($db->errorMessage());
    echo 'Affected rows: '.$affected_rows."<br>";

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
