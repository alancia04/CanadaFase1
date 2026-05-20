<?php
// helper procedurali sparsi, usati in più pagine.

require_once __DIR__ . '/lang.php';

// shortcut per htmlspecialchars. da chiamare sempre prima di stampare $_POST/DB.

// TODO: implementare il corpo di ogni funzione
function e($v): string {

}

function redirect(string $path): void {

}

function flash_set(string $msg): void {

}

function flash(): string {
    
}

function format_date_it(?string $sqlDate): string {
    
}

function format_datetime_it(?string $sqlDt): string {
    
}

function barra_utente(): string {
    
}

function footer_html(): string {

}

function topbar_html(): string {
    
}

function admin_breadcrumb(string $current): string {
   
}

function subnav_admin(string $title, array $items, string $current_url): string {
    
}

function subnav_anagrafica(string $current_url): string {
    
}

function subnav_attivita(string $current_url): string {
    
}

function banner_certificato(?array $cert): string {
    
}

function slugify(string $s): string {
    
}
