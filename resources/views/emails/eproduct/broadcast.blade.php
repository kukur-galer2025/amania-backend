<x-mail::message>
# Halo!

Kabar baik untukmu! **{{ $product->author->name ?? 'Kreator kami' }}** baru saja merilis produk digital terbaru.

## {{ $product->title }}

@if($product->description)
{!! Str::limit(strip_tags($product->description), 200) !!}
@endif

Segera dapatkan selagi masih baru!

<x-mail::button :url="config('app.frontend_url', 'http://localhost:3000') . '/e-products/' . $product->slug">
Lihat Produk Sekarang
</x-mail::button>

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
