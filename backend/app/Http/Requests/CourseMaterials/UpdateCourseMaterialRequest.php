<?php

namespace App\Http\Requests\CourseMaterials;

class UpdateCourseMaterialRequest extends StoreCourseMaterialRequest
{
    // Same shape as Store — section/subject aren't expected to change after
    // creation in practice, but allowing it keeps this a single edit form.
}
