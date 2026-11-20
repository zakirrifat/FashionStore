<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use LogsAdminActivity;

    public const TOPICS = ['Orders', 'Delivery', 'Returns & refunds', 'Payments', 'Products & care', 'Account', 'General'];

    protected $fillable = ['topic', 'question', 'answer', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function answerHtml(): string
    {
        return RichContentRenderer::make($this->answer)->toHtml();
    }
}
