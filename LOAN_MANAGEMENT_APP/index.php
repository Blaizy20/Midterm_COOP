<?php
/**
 * Refactored Application Entry Point
 * 
 * After removing the customer web portal, the main entry point now redirects to the
 * staff portal login. Customer functionality has been moved to a mobile application.
 * 
 * The database and backend services are ready for mobile API integration.
 */

// Redirect to staff portal login (web portal is staff-only)
header('Location: /staff/login.php');
exit;
