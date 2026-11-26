<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    private const PER_PAGE = 24;

    public function home(): View
    {
        $sections = Setting::get('home_sections');
        if (! empty($sections)) {
            return view('shop.home-sections', ['sections' => $this->resolveSections($sections)]);
        }

        // Automatic home page until sections are set up in Admin › Content › Home page.
        $base = fn () => Product::visible()->with(['colours.images', 'colours.variants'])->withCount('colours');

        $featured = $base()->where('is_featured', true)->latest('updated_at')->take(8)->get();

        $newArrivals = Collection::where('slug', 'new-arrivals')->where('is_active', true)->first();
        $latest = $newArrivals
            ? $base()->whereHas('collections', fn ($q) => $q->whereKey($newArrivals->id))->latest()->take(8)->get()
            : collect();
        if ($latest->isEmpty()) {
            $latest = $base()->latest()->take(8)->get();
        }

        $collections = Collection::where('is_active', true)->orderBy('sort_order')->take(4)->get();

        return view('shop.home', [
            'hero' => $featured->first() ?? $latest->first(),
            'featured' => $featured,
            'latest' => $latest,
            'collections' => $collections,
        ]);
    }

    /** Add product lists and automatic links to "Product row" sections; drop broken ones. */
    private function resolveSections(array $sections): array
    {
        return collect($sections)->map(function (array $section) {
            if (($section['type'] ?? null) !== 'products') {
                return $section;
            }

            $d = $section['data'];
            $query = Product::visible()->with(['colours.images', 'colours.variants'])->withCount('colours');
            $autoLink = null;

            switch ($d['source'] ?? 'latest') {
                case 'featured':
                    $query->where('is_featured', true)->latest('updated_at');
                    break;
                case 'sale':
                    return null; // prices are hidden in the shop, so "on sale" rows are not shown
                case 'collection':
                    $collection = Collection::where('is_active', true)->find($d['collection_id'] ?? 0);
                    if (! $collection) {
                        return null;
                    }
                    $query->whereHas('collections', fn ($q) => $q->whereKey($collection->id))->latest();
                    $autoLink = route('shop.collection', $collection);
                    break;
                case 'category':
                    $category = Category::where('is_active', true)->find($d['category_id'] ?? 0);
                    if (! $category) {
                        return null;
                    }
                    $ids = $category->descendantAndSelfIds();
                    $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids))->latest();
                    $autoLink = route('shop.category', $category);
                    break;
                default:
                    $query->latest();
            }

            $section['data']['products'] = $query->take((int) ($d['limit'] ?? 4))->get();
            $section['data']['link'] = filled($d['link'] ?? null) ? $d['link'] : $autoLink;

            return $section['data']['products']->isEmpty() ? null : $section;
        })->filter()->values()->all();
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        $category->load(['parent.parent', 'children' => fn ($q) => $q->where('is_active', true)]);
        $ids = $category->descendantAndSelfIds();

        $query = Product::visible()->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));

        return $this->listing($request, $query, [
            'title' => $category->name,
            'heading' => $category->parent ? self::possessive($category->parent->name).' '.$category->name : $category->name,
            'description' => $category->description,
            'chips' => $category->children->isNotEmpty() ? $category->children : ($category->parent?->children()->where('is_active', true)->get() ?? collect()),
            'current' => $category,
            'breadcrumbs' => $this->breadcrumbs($category),
            'seoTitle' => $category->seo_title,
            'seoDescription' => $category->seo_description,
            'headerImage' => $category->header_image,
        ]);
    }

    public function collection(Request $request, Collection $collection): View
    {
        abort_unless($collection->is_active, 404);

        $query = Product::visible()->whereHas('collections', fn ($q) => $q->whereKey($collection->id));

        return $this->listing($request, $query, [
            'title' => $collection->name,
            'heading' => $collection->name,
            'description' => $collection->description,
            'chips' => collect(),
            'current' => null,
            'breadcrumbs' => [],
            'banner' => $collection->image,
        ]);
    }

    public function search(Request $request): View
    {
        $term = self::searchTerm($request);

        $query = Product::visible()->when($term !== '', function (Builder $q) use ($term) {
            $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('style_code', 'like', "%{$term}%")
                ->orWhere('tagline', 'like', "%{$term}%")
                ->orWhereHas('categories', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('colours', fn ($c) => $c->where('name', 'like', "%{$term}%")));
        }, fn ($q) => $q->whereRaw('1 = 0'));

        return $this->listing($request, $query, [
            'title' => $term === '' ? 'Search' : "Search: {$term}",
            'heading' => $term === '' ? 'Search' : '“'.$term.'”',
            'description' => null,
            'chips' => collect(),
            'current' => null,
            'breadcrumbs' => [],
            'searchTerm' => $term,
        ]);
    }

    /** Live results for the search overlay. */
    public function suggest(Request $request): \Illuminate\Http\JsonResponse
    {
        $term = self::searchTerm($request);
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'categories' => [], ...$this->searchDiscover()]);
        }

        $products = Product::visible()
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('style_code', 'like', "%{$term}%")
                ->orWhere('tagline', 'like', "%{$term}%")
                ->orWhereHas('colours', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('categories', fn ($c) => $c->where('name', 'like', "%{$term}%")))
            ->with(['colours.images', 'colours.variants'])
            ->orderByDesc('is_featured')->latest()
            ->take(9)->get();

        // Only categories the shop menu can reach (the category and its parents are all active).
        $categories = \App\Models\Category::where('is_active', true)
            ->where('name', 'like', "%{$term}%")
            ->with('parent.parent')
            ->orderBy('sort_order')
            ->take(20)->get()
            ->filter(fn ($c) => ($c->parent?->is_active ?? true) && ($c->parent?->parent?->is_active ?? true))
            ->take(5)
            ->map(fn ($c) => [
                'name' => collect([$c->parent?->parent?->name, $c->parent?->name, $c->name])->filter()->implode(' / '),
                'url' => route('shop.category', $c),
            ])->values();

        return response()->json([
            'results' => $products->map(fn (Product $p) => $this->searchCard($p)),
            'categories' => $categories,
        ]);
    }

    /** Products shown before typing: featured ones, or the newest if none are featured. */
    private function searchDiscover(): array
    {
        $query = Product::visible()->with('colours.images')->latest();
        $products = (clone $query)->where('is_featured', true)->take(4)->get();
        $label = 'Featured';

        if ($products->isEmpty()) {
            $products = $query->take(4)->get();
            $label = 'New in';
        }

        return [
            'featured' => $products->map(function (Product $p) {
                $card = $this->searchCard($p);
                $colour = $p->colours->first();

                return $card + ['enquiry' => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'image' => $card['image'],
                    'colourId' => $colour?->id,
                    'colour' => $colour?->name,
                ]];
            })->values(),
            'featured_label' => $label,
        ];
    }

    private function searchCard(Product $p): array
    {
        $colour = $p->colours->first();

        return [
            'name' => $p->name,
            'url' => $p->url(),
            'colour' => $colour?->name,
            'image' => ($path = $colour?->images->first()?->path) ? \App\Support\Img::url($path, 400) : null,
        ];
    }

    public function product(Request $request, Product $product): View
    {
        abort_unless(Product::visible()->whereKey($product->id)->exists(), 404);

        $product->load(['colours.images', 'colours.variants', 'categories.parent.parent']);
        abort_if($product->colours->isEmpty(), 404);

        $colour = $product->colours->firstWhere('code', $request->query('colour')) ?? $product->colours->first();

        // Deepest category gives the most useful breadcrumb.
        $category = $product->categories->sortByDesc(fn ($c) => $c->parent?->parent ? 2 : ($c->parent ? 1 : 0))->first();

        $related = Product::visible()
            ->whereKeyNot($product->id)
            ->when($category, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($category->id)))
            ->with(['colours.images', 'colours.variants'])->withCount('colours')
            ->inRandomOrder()->take(4)->get();

        return view('shop.product', [
            'product' => $product,
            'colour' => $colour,
            'breadcrumbs' => $category ? $this->breadcrumbs($category) : [],
            'related' => $related,
            'sizeGuide' => $product->resolveSizeGuide(),
        ]);
    }

    /** Shared category / collection / search page with size + colour filters and sorting. */
    private function listing(Request $request, Builder $query, array $data): View
    {
        // Filter options come from the unfiltered result set.
        $productIds = (clone $query)->pluck('products.id');
        $sizes = ProductVariant::query()
            ->whereHas('colour', fn ($q) => $q->whereIn('product_id', $productIds))
            ->distinct()->pluck('size')
            ->sortBy(fn ($s) => self::sizeRank($s))->values();
        $colours = \App\Models\ProductColour::whereIn('product_id', $productIds)->distinct()->orderBy('name')->pluck('name');

        $selectedSizes = array_filter((array) $request->query('size', []));
        $selectedColours = array_filter((array) $request->query('colour', []));
        $sort = in_array($request->query('sort'), ['featured', 'newest'], true) ? $request->query('sort') : 'featured';

        $query
            ->when($selectedSizes, fn ($q) => $q->whereHas('colours.variants', fn ($v) => $v->whereIn('size', $selectedSizes)->where('stock', '>', 0)))
            ->when($selectedColours, fn ($q) => $q->whereHas('colours', fn ($c) => $c->whereIn('name', $selectedColours)));

        match ($sort) {
            'newest' => $query->latest(),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        $products = $query->with(['colours.images', 'colours.variants'])->withCount('colours')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('shop.listing', $data + [
            'products' => $products,
            'sizes' => $sizes,
            'colours' => $colours,
            'selectedSizes' => $selectedSizes,
            'selectedColours' => $selectedColours,
            'sort' => $sort,
            'banner' => $data['banner'] ?? null,
            'headerImage' => $data['headerImage'] ?? null,
            'searchTerm' => $data['searchTerm'] ?? null,
        ]);
    }

    /** The visitor's search text, cut to 100 characters (longer text only makes the LIKE search slow). */
    private static function searchTerm(Request $request): string
    {
        $q = $request->query('q', '');

        return is_string($q) ? trim(mb_substr($q, 0, 100)) : '';
    }

    /** "Woman’s Jeans", but "Girls’ Jeans" rather than "Girls’s Jeans". */
    private static function possessive(string $name): string
    {
        return str_ends_with(mb_strtolower($name), 's') ? $name.'’' : $name.'’s';
    }

    private function breadcrumbs(Category $category): array
    {
        $crumbs = [];
        $c = $category;
        $guard = 0;
        while ($c && $guard++ < 10) {
            array_unshift($crumbs, ['name' => $c->name, 'url' => route('shop.category', $c)]);
            $c = $c->parent;
        }

        return $crumbs;
    }

    public static function sizeRank(string $size): string
    {
        $order = ['XXXS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        $i = array_search(strtoupper($size), $order, true);

        return $i === false ? 'z'.str_pad($size, 10, '0', STR_PAD_LEFT) : 'a'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    }
}
