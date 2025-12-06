<?php
class IDHelper {

    public static function generate($conn, $table, $col, $type) {
        $year = date("y");
        $prefix = $year . $type; 
        
        $sql = "SELECT $col 
                FROM $table 
                WHERE $col 
                LIKE ? 
                ORDER BY $col 
                DESC LIMIT 1";
        
        $search_param = $prefix . "%";
        
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die(json_encode([
                'status' => 'error', 
                'message' => 'Helper SQL Error: ' . $conn->error
            ]));
        }
        
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $last_id = $row[$col];
            $prefix_length = strlen($prefix);
            $number_part = intval(substr($last_id, $prefix_length));
            
            $new_number = $number_part + 1;
        } 
        else {
            $new_number = 1;
        }
        
        return $prefix . str_pad($new_number, 4, "0", STR_PAD_LEFT);
    }
}