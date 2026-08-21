<?php
/**
 * Public site lives under frontend/ after the backend/frontend split.
 * Keeps http://localhost:8080/SV%20Auto%20Truck%20Repair/ opening the homepage.
 */
header('Location: frontend/', true, 302);
exit;
