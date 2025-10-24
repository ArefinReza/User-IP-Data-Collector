
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch session ID and total time spent from the POST data
    $session_id = $_POST['session_id'] ?? '';
    $total_time_spent = $_POST['total_time_spent'] ?? 0;

    if (!empty($session_id)) {
        include 'db_config.php'; // Include database configuration

        // Update the end_time and total_time_spent for the corresponding session
        $stmt = $conn->prepare("
            UPDATE visitors 
            SET end_time = NOW(), total_time_spent = ? 
            WHERE session_id = ? AND end_time IS NULL
        ");
        $stmt->bind_param("is", $total_time_spent, $session_id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "End time updated successfully."]);
        } else {
            error_log("Error updating end_time and total_time_spent: " . $stmt->error);
            echo json_encode(["status" => "error", "message" => "Database update failed."]);
        }

        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid session ID."]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>
