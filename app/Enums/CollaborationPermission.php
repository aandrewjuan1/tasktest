<?php

namespace App\Enums;

enum CollaborationPermission: string
{
    case View = 'view';
    case Comment = 'comment';
    case Edit = 'edit';
}
