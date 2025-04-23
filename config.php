<?php

return [
    // 🛢️ Database connection
    'db_host' => '127.0.0.1',
    'db_port' => '4002',
    'db_name' => 'ID304765_authkiemkracht',
    'db_user' => 'ID304765_authkiemkracht',
    'db_pass' => 'S545n79S2o4nWD6KJg5g',

    // 🏪 winkel waar je je bevind
    'current_store' => 'Waasmunster',

    // 🧾 Log output 
    'log_file' => __DIR__ . '/printqueue.log',

    // 🖨️ naam van de printer (used with SumatraPDF)
    'printer_name' => 'star'  // 👈 Make sure this matches exactly with your installed printer name
];
