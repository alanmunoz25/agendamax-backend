<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Determine whether the user can view any courses.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->business_id !== null;
    }

    /**
     * Determine whether the user can view the course.
     */
    public function view(User $user, Course $course): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $course->business_id === $user->business_id;
    }

    /**
     * Determine whether the user can create courses.
     */
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $user->business_id !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can update the course.
     */
    public function update(User $user, Course $course): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $course->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the course.
     */
    public function delete(User $user, Course $course): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $course->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the course.
     */
    public function restore(User $user, Course $course): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $course->business_id === $user->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the course.
     */
    public function forceDelete(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }
}
