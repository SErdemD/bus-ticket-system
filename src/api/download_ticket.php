<?php
// src/api/download_ticket.php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../scripts/auth_helper.php';

// Note: This requires TCPDF library. Install via: composer require tecnickcom/tcpdf
// Or download from: https://tcpdf.org/
require_once __DIR__ . '/../tcpdf/tcpdf.php'; // Adjust path as needed

require_login();

if (is_admin() || is_company()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $user_id = $_SESSION['user_id'];
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $ticket_id = $input['ticket_id'] ?? '';

    if (!$ticket_id) {
        throw new Exception('Ticket ID is required.');
    }

    // Fetch ticket details with all related information
    $ticket_stmt = $pdo->prepare("
        SELECT 
            t.id as ticket_id,
            t.status,
            t.total_price,
            t.created_at as booking_date,
            tr.departure_city,
            tr.destination_city,
            tr.departure_time,
            tr.arrival_time,
            tr.bus_type,
            bc.name as company_name,
            bc.logo_path as company_logo,
            u.full_name as passenger_name,
            u.email as passenger_email,
            GROUP_CONCAT(bs.seat_number, ',') as seats
        FROM Tickets t
        JOIN Trips tr ON t.trip_id = tr.id
        JOIN Bus_Company bc ON tr.company_id = bc.id
        JOIN User u ON t.user_id = u.id
        LEFT JOIN Booked_Seats bs ON bs.ticket_id = t.id
        WHERE t.id = ? AND t.user_id = ?
        GROUP BY t.id
    ");
    $ticket_stmt->execute([$ticket_id, $user_id]);
    $ticket = $ticket_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('Ticket not found or access denied.');
    }

    // Process seats
    $seats = $ticket['seats'] ? explode(',', $ticket['seats']) : [];
    sort($seats, SORT_NUMERIC);
    
    // Format dates
    $departure = new DateTime($ticket['departure_time']);
    $arrival = new DateTime($ticket['arrival_time']);
    $booking = new DateTime($ticket['booking_date']);

    // Create PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Bus Ticket System');
    $pdf->SetAuthor($ticket['company_name']);
    $pdf->SetTitle('Bus Ticket - ' . $ticket['ticket_id']);
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Colors
    $primaryColor = array(59, 130, 246); // Blue
    $darkColor = array(45, 55, 72);
    $lightColor = array(226, 232, 240);
    
    // Header with company name
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Rect(0, 0, 210, 40, 'F');
    
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 24);
    $pdf->SetXY(15, 15);
    $pdf->Cell(0, 10, $ticket['company_name'], 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetXY(15, 27);
    $pdf->Cell(0, 6, 'Bus Ticket', 0, 1, 'C');
    
    // Reset text color
    $pdf->SetTextColor(0, 0, 0);
    
    // Ticket status badge
    $statusY = 50;
    if ($ticket['status'] === 'ACTIVE') {
        $pdf->SetFillColor(16, 185, 129);
        $statusText = 'ACTIVE';
    } elseif ($ticket['status'] === 'CANCELLED') {
        $pdf->SetFillColor(239, 68, 68);
        $statusText = 'CANCELLED';
    } else {
        $pdf->SetFillColor(107, 114, 128);
        $statusText = $ticket['status'];
    }
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(160, $statusY);
    $pdf->Cell(35, 8, $statusText, 0, 0, 'C', true, '', 0, false, 'T', 'M');
    
    // Ticket ID
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(15, $statusY);
    $pdf->Cell(0, 5, 'Ticket ID: ' . strtoupper(substr($ticket['ticket_id'], 0, 16)), 0, 1, 'L');
    
    // Passenger Information Box
    $pdf->SetY($statusY + 15);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Passenger Information', 0, 1, 'L');
    
    $pdf->SetFillColor($lightColor[0], $lightColor[1], $lightColor[2]);
    $pdf->Rect(15, $pdf->GetY(), 180, 25, 'F');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetY($pdf->GetY() + 5);
    $pdf->Cell(60, 6, 'Name:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, $ticket['passenger_name'], 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 6, 'Email:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, $ticket['passenger_email'], 0, 1, 'L');
    
    // Trip Details
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Trip Details', 0, 1, 'L');
    
    // From/To Section
    $tripY = $pdf->GetY();
    
    // Departure Box
    $pdf->SetFillColor(240, 249, 255);
    $pdf->Rect(15, $tripY, 85, 35, 'F');
    $pdf->SetXY(20, $tripY + 5);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 5, 'DEPARTURE FROM', 0, 1, 'L');
    
    $pdf->SetXY(20, $tripY + 10);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 7, $ticket['departure_city'], 0, 1, 'L');
    
    $pdf->SetXY(20, $tripY + 18);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, $departure->format('M d, Y'), 0, 1, 'L');
    
    $pdf->SetXY(20, $tripY + 23);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell(0, 6, $departure->format('H:i'), 0, 1, 'L');
    
    // Arrow
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetXY(100, $tripY + 13);
    $pdf->Cell(10, 10, '→', 0, 0, 'C');
    
    // Arrival Box
    $pdf->SetFillColor(240, 253, 244);
    $pdf->Rect(110, $tripY, 85, 35, 'F');
    $pdf->SetXY(115, $tripY + 5);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 5, 'ARRIVAL AT', 0, 1, 'L');
    
    $pdf->SetXY(115, $tripY + 10);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 7, $ticket['destination_city'], 0, 1, 'L');
    
    $pdf->SetXY(115, $tripY + 18);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, $arrival->format('M d, Y'), 0, 1, 'L');
    
    $pdf->SetXY(115, $tripY + 23);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(16, 185, 129);
    $pdf->Cell(0, 6, $arrival->format('H:i'), 0, 1, 'L');
    
    // Additional Details
    $pdf->SetY($tripY + 45);
    $pdf->SetTextColor(0, 0, 0);
    
    $detailsY = $pdf->GetY();
    $pdf->SetFont('helvetica', '', 10);
    
    // Left column
    $pdf->SetXY(15, $detailsY);
    $pdf->Cell(40, 7, 'Bus Type:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 7, $ticket['bus_type'], 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 7, 'Seat Number(s):', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 7, implode(', ', $seats), 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 7, 'Total Fare:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell(0, 7, '$' . number_format($ticket['total_price'], 2), 0, 1, 'L');
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 7, 'Booking Date:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, $booking->format('M d, Y H:i'), 0, 1, 'L');
    
    // Footer/Notice
    $pdf->SetY(260);
    $pdf->SetFillColor(250, 250, 250);
    $pdf->Rect(15, 260, 180, 20, 'F');
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetXY(15, 265);
    $pdf->MultiCell(180, 4, 
        "Important: Please arrive at the boarding point at least 15 minutes before departure time.\n" .
        "Carry a valid ID proof for verification. This ticket is non-transferable.",
        0, 'C', false);
    
    // Output PDF
    $filename = 'ticket_' . substr($ticket['ticket_id'], 0, 8) . '.pdf';
    $pdf->Output($filename, 'D'); // D = download

} catch (Exception $e) {
    error_log("Download ticket error [User: {$user_id}]: " . $e->getMessage());
    
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in download ticket: " . $e->getMessage());
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}