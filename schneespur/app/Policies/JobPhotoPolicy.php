<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\JobPhoto;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class JobPhotoPolicy
{
    /**
     * May this viewer see this job photo?
     *
     * Three guards share this method — the `web` guard (admins and drivers), the
     * `customer` guard (portal), and nobody at all (guest). The agreed rules:
     *
     *   Admin           every photo
     *   Driver          only photos of jobs they drove themselves
     *   Customer        only their own jobs, and only while portal_show_photos is on
     *   Guest           nothing
     *
     * One branch per guard rather than a chain of ?-> accesses: a Customer must
     * never fall through into the driver comparison, where the two id spaces
     * would silently line up (customer #7 seeing driver #7's jobs).
     */
    public function view(?Authenticatable $viewer, JobPhoto $photo): bool
    {
        if ($viewer === null) {
            return false;
        }

        $job = $photo->job;

        if ($job === null) {
            // Unreachable today and deliberately untested: job_photos.job_id is
            // cascadeOnDelete, so a photo cannot outlive its job. Kept because
            // without it $job->user_id below would raise a 500 instead of
            // refusing — and a nullable job_id or a soft delete would make this
            // reachable overnight. An orphan belongs to nobody, admin included.
            return false;
        }

        if ($viewer instanceof User) {
            return $viewer->isAdmin() || $viewer->id === $job->user_id;
        }

        if ($viewer instanceof Customer) {
            // Both halves are required. Right customer with the toggle off, or
            // the toggle on for someone else's job, are each a leak.
            return $viewer->portal_show_photos && $viewer->id === $job->customer_id;
        }

        // A guard we do not know about is not a reason to hand out photos.
        return false;
    }
}
