<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_PHOTO_KB = 2048;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(User $user): array
    {
        $user->loadMissing('employee.department');

        $employee = $user->employee;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role?->label(),
                'role_value' => $user->role?->value,
                'status' => $user->status?->label(),
                'status_value' => $user->status?->value,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            ],
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'last_name' => $employee->last_name,
                'suffix' => $employee->suffix,
                'full_name' => $employee->fullName(),
                'email' => $employee->email,
                'contact_number' => $employee->contact_number,
                'address' => $employee->address,
                'birth_date' => $employee->birth_date?->toDateString(),
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'employment_status' => $employee->employment_status?->label(),
                'date_hired' => $employee->date_hired?->toDateString(),
                'photo_url' => $employee->photoUrl(),
                'has_photo' => filled($employee->photo),
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePersonalInfo(User $user, array $data): Employee
    {
        $employee = $this->requireEmployee($user);

        return DB::transaction(function () use ($user, $employee, $data) {
            $changes = [];

            foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'contact_number', 'address', 'birth_date'] as $field) {
                if (array_key_exists($field, $data)) {
                    $changes[$field] = $data[$field];
                }
            }

            if (array_key_exists('email', $data)) {
                $changes['email'] = $data['email'];
            }

            $employee->fill($changes);
            $employee->full_name = $this->composeFullName($employee);
            $employee->save();

            $userUpdates = ['name' => $employee->fullName()];
            if (array_key_exists('email', $data)) {
                $userUpdates['email'] = $data['email'];
            }
            $user->update($userUpdates);

            $updatedFields = array_keys(array_filter($changes, fn ($v) => $v !== null && $v !== ''));
            if (array_key_exists('email', $data)) {
                $updatedFields[] = 'email';
            }

            $this->auditLogger->log(
                $user,
                'profile_updated',
                'Profile',
                $employee->id,
                'Profile updated: '.implode(', ', array_unique($updatedFields)).'.'
            );

            return $employee->fresh(['department']);
        });
    }

    public function storePhoto(User $user, UploadedFile $file): Employee
    {
        $employee = $this->requireEmployee($user);
        $this->assertValidPhoto($file);

        return DB::transaction(function () use ($user, $employee, $file) {
            $path = $this->writePhoto($employee, $file);
            $previous = $employee->photo;

            $employee->update(['photo' => $path]);

            if ($previous && $previous !== $path) {
                Storage::disk('public')->delete($previous);
            }

            $this->auditLogger->log($user, 'profile_photo_updated', 'Profile', $employee->id, 'Profile picture updated.');

            return $employee->fresh(['department']);
        });
    }

    public function removePhoto(User $user): Employee
    {
        $employee = $this->requireEmployee($user);

        return DB::transaction(function () use ($user, $employee) {
            $previous = $employee->photo;
            $employee->update(['photo' => null]);

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }

            $this->auditLogger->log($user, 'profile_photo_removed', 'Profile', $employee->id, 'Profile picture removed.');

            return $employee->fresh(['department']);
        });
    }

    private function requireEmployee(User $user): Employee
    {
        $employee = $user->employee;

        abort_unless($employee, 403, 'Your account is not linked to an employee profile.');

        return $employee;
    }

    private function composeFullName(Employee $employee): string
    {
        $middle = $employee->middle_name ? ' '.$employee->middle_name : '';
        $suffix = $employee->suffix ? ' '.$employee->suffix : '';

        return trim($employee->last_name.', '.$employee->first_name.$middle.$suffix);
    }

    private function assertValidPhoto(UploadedFile $file): void
    {
        abort_unless($file->isValid(), 422, 'The uploaded file is invalid.');
        abort_unless($file->getSize() <= self::MAX_PHOTO_KB * 1024, 422, 'Profile photo must not exceed 2 MB.');

        $mime = $file->getMimeType();
        abort_unless(in_array($mime, self::ALLOWED_MIMES, true), 422, 'Profile photo must be JPG, PNG, or WEBP.');
    }

    private function writePhoto(Employee $employee, UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = 'photos/employees/'.$employee->id;

        return $file->storeAs($directory, $filename, 'public');
    }
}
