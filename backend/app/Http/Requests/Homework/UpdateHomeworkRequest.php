<?php

namespace App\Http\Requests\Homework;

class UpdateHomeworkRequest extends StoreHomeworkRequest
{
    // Same shape as Store — section/subject aren't expected to change after
    // creation in practice, but allowing it keeps this a single edit form
    // rather than forcing "delete and recreate" for a teacher's typo fix.
}
