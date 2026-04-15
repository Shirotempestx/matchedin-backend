<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\InAppNotification;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'preferred_language',
        'password',
        'role',
        'status',
        'country',
        'work_mode',
        'salary_min',
        'profile_type',
        'skill_ids',
        'company_name',
        'industry',
        'company_size',
        'website',
        // Student new fields
        'title',
        'bio',
        'phone',
        'cv_url',
        'avatar_url',
        'education_level',
        'university',
        'availability',
        'linkedin_url',
        'portfolio_url',
        'banner_url',
        // Enterprise new fields
        'description',
        'logo_url',
        'contact_phone',
        // Subscription
        'subscription_tier',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'skill_ids' => 'array',
        ];
    }

    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class);
    }

    public function postulations(): HasMany
    {
        return $this->hasMany(Postulation::class);
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    // Relationships for 'Follow Enterprise' feature
    public function followingEnterprises()
    {
        return $this->belongsToMany(User::class, 'enterprise_followers', 'student_id', 'enterprise_id')->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'enterprise_followers', 'enterprise_id', 'student_id')->withTimestamps();
    }

    // Relationships for 'Save Student' feature
    public function savedStudents()
    {
        return $this->belongsToMany(User::class, 'saved_students', 'enterprise_id', 'student_id')->withTimestamps();
    }

    public function savedByEnterprises()
    {
        return $this->belongsToMany(User::class, 'saved_students', 'student_id', 'enterprise_id')->withTimestamps();
    }
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    // ─── Chat Relationships ───────────────────────────────────────

    /**
     * All conversations where this user is a participant.
     */
    public function conversations()
    {
        return Conversation::where('participant_one_id', $this->id)
            ->orWhere('participant_two_id', $this->id)
            ->orderByDesc('last_message_at');
    }

    // ─── Role & Tier Helpers ──────────────────────────────────────

    public function isEnterprise(): bool
    {
        return $this->role === 'enterprise';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function tierIs(string $tier): bool
    {
        return $this->subscription_tier === $tier;
    }

    public function isFreeUser(): bool
    {
        return empty($this->subscription_tier);
    }
}
