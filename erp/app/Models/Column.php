<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Column extends Model
{
    protected $fillable = ['board_id', 'name', 'position', 'color'];

    public function tasks()
    {
        return $this->hasMany(Task::class)->orderBy('created_at', 'desc');
    }

    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Get color details with Tailwind classes
     */
    public function getColorDetailsAttribute()
    {
        $colors = [
            'gray' => [
                'bg' => 'bg-gray-100',
                'border' => 'border-gray-300',
                'text' => 'text-gray-700',
                'badge' => 'bg-gray-200',
                'gradient' => 'linear-gradient(135deg, #F3F4F6 0%, #9CA3AF 100%)'
            ],
            'blue' => [
                'bg' => 'bg-blue-100',
                'border' => 'border-blue-300',
                'text' => 'text-blue-700',
                'badge' => 'bg-blue-200',
                'gradient' => 'linear-gradient(135deg, #DBEAFE 0%, #60A5FA 100%)'
            ],
            'purple' => [
                'bg' => 'bg-purple-100',
                'border' => 'border-purple-300',
                'text' => 'text-purple-700',
                'badge' => 'bg-purple-200',
                'gradient' => 'linear-gradient(135deg, #EDE9FE 0%, #A78BFA 100%)'
            ],
            'green' => [
                'bg' => 'bg-green-100',
                'border' => 'border-green-300',
                'text' => 'text-green-700',
                'badge' => 'bg-green-200',
                'gradient' => 'linear-gradient(135deg, #D1FAE5 0%, #34D399 100%)'
            ],
            'yellow' => [
                'bg' => 'bg-yellow-100',
                'border' => 'border-yellow-300',
                'text' => 'text-yellow-700',
                'badge' => 'bg-yellow-200',
                'gradient' => 'linear-gradient(135deg, #FEF3C7 0%, #FBBF24 100%)'
            ],
            'red' => [
                'bg' => 'bg-red-100',
                'border' => 'border-red-300',
                'text' => 'text-red-700',
                'badge' => 'bg-red-200',
                'gradient' => 'linear-gradient(135deg, #FEE2E2 0%, #F87171 100%)'
            ],
            'indigo' => [
                'bg' => 'bg-indigo-100',
                'border' => 'border-indigo-300',
                'text' => 'text-indigo-700',
                'badge' => 'bg-indigo-200',
                'gradient' => 'linear-gradient(135deg, #E0E7FF 0%, #818CF8 100%)'
            ],
            'pink' => [
                'bg' => 'bg-pink-100',
                'border' => 'border-pink-300',
                'text' => 'text-pink-700',
                'badge' => 'bg-pink-200',
                'gradient' => 'linear-gradient(135deg, #FCE7F3 0%, #F472B6 100%)'
            ],
            'orange' => [
                'bg' => 'bg-orange-100',
                'border' => 'border-orange-300',
                'text' => 'text-orange-700',
                'badge' => 'bg-orange-200',
                'gradient' => 'linear-gradient(135deg, #FFEDD5 0%, #FB923C 100%)'
            ],
            'teal' => [
                'bg' => 'bg-teal-100',
                'border' => 'border-teal-300',
                'text' => 'text-teal-700',
                'badge' => 'bg-teal-200',
                'gradient' => 'linear-gradient(135deg, #CCFBF1 0%, #2DD4BF 100%)'
            ],
        ];

        return $colors[$this->color] ?? $colors['blue'];
    }
}
