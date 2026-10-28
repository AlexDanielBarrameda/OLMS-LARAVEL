<?php

// Only the settings this app changes; everything else uses Livewire's defaults (vendor/livewire/livewire/config/livewire.php).
// Livewire merges this file one level deep, so the whole temporary_file_upload block is repeated here.
return [

    'temporary_file_upload' => [
        'disk' => null,
        // Every upload in the app is a GCash screenshot. Checked when the file arrives, before it's kept in livewire-tmp,
        // so nobody can fill the disk with large or non-image files (Livewire's default allows any file up to 12 MB).
        'rules' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        'directory' => null,
        // 10 uploads a minute per user is plenty for a receipt; Livewire's default is 60
        'middleware' => 'throttle:10,1',
        'preview_mimes' => ['png', 'jpg', 'jpeg', 'webp'],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
