<?php

namespace App\Enums;

enum Role: string
{
    case Speaker = 'speaker';
    case Reviewer = 'reviewer';
    case Admin = 'admin';
}
