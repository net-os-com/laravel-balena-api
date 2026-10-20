<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\PineQuery;

beforeEach(function () {
    $this->http = new Factory;
    $this->http->preventStrayRequests();
    $this->http->fake(fn (Request $request) => $this->http->response(
        str_contains($request->url(), '/$count?') ? '3' : ['d' => []],
    ));
    $this->client = new BalenaClient($this->http, 'test-token', 'https://balena.test', retryTimes: 1);
    $this->query = new PineQuery($this->client, 'device');
});

it('sends literal select separators on a keyed GET', function () {
    $this->query->select('uuid', 'is_online', 'overall_status', 'status')
        ->find(['uuid' => '47d19f70da3da9b1c96fa35cab20afc9']);

    $this->http->assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === "https://balena.test/v7/device(uuid='47d19f70da3da9b1c96fa35cab20afc9')?\$select=uuid,is_online,overall_status,status"
        && $request->body() === '');
    $this->http->assertSentCount(1);
});

it('sends literal select separators with a filtered first GET', function () {
    $this->query->select('uuid', 'is_online')->where('uuid', 'abc')->first();

    $this->http->assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === "https://balena.test/v7/device?\$select=uuid,is_online&\$filter=uuid%20eq%20'abc'&\$top=1"
        && $request->body() === '');
});

it('preserves grouping, function commas and relation traversal in filters', function () {
    $query = $this->query
        ->where(fn ($q) => $q->where('is_online', true)->orWhere('device_name', 'contains', 'sensor,one'))
        ->whereRelation('belongs_to__application', fn ($q) => $q->where('slug', 'org/fleet'));

    expect($query->toUrl())->toBe('https://balena.test/v7/device?$filter='
        ."(is_online%20eq%20true%20or%20contains(device_name,'sensor%2Cone'))"
        ."%20and%20belongs_to__application/any(a:a/slug%20eq%20'org%2Ffleet')");

    $query->get();
    $this->http->assertSent(fn (Request $request) => $request->url() === $query->toUrl()
        && $request->method() === 'GET');
});

it('encodes literal contents without turning them into query syntax', function (string $literal, string $encoded) {
    $query = $this->query->where('device_name', $literal)->where('is_online', true);
    $expected = "https://balena.test/v7/device?\$filter=device_name%20eq%20'{$encoded}'%20and%20is_online%20eq%20true";

    expect($query->toUrl())->toBe($expected);
    $query->get();
    $this->http->assertSent(fn (Request $request) => $request->url() === $expected
        && $request->method() === 'GET');
})->with([
    'spaces and plus' => ['a b+c', 'a%20b%2Bc'],
    'apostrophes' => ["O'Brien's", "O''Brien''s"],
    'quotes at boundaries' => ["'a'", "''a''"],
    'only quotes' => ["''", "''''"],
    'empty string' => ['', ''],
    'query injection characters' => ['x&$top=1#?', 'x%26%24top%3D1%23%3F'],
    'OData punctuation inside a literal' => [',();=/:$*@', '%2C%28%29%3B%3D%2F%3A%24%2A%40'],
    'percent sequences stay literal' => ['%27%2C%20', '%2527%252C%2520'],
    'Unicode and double quotes' => ['café 雪 "hi"', 'caf%C3%A9%20%E9%9B%AA%20%22hi%22'],
    'control characters' => ["a\r\n\tb", 'a%0D%0A%09b'],
]);

it('preserves nested expand syntax and multiple orderings with pagination', function () {
    $query = $this->query->select('uuid', 'status')
        ->expand('belongs_to__application', fn ($q) => $q
            ->select('app_name', 'slug')
            ->where('app_name', "Roy's fleet & test,one")
            ->expand('organization', fn ($q) => $q->select('id', 'handle'))
            ->orderBy('app_name')->orderByDesc('id')->take(2)->skip(1))
        ->expand('device_tag')
        ->orderByDesc('id')->orderBy('device_name')->take(10)->skip(20);

    $expected = 'https://balena.test/v7/device?$select=uuid,status'
        .'&$expand=belongs_to__application($select=app_name,slug;'
        ."\$filter=app_name%20eq%20'Roy''s%20fleet%20%26%20test%2Cone';"
        .'$expand=organization($select=id,handle);$orderby=app_name%20asc,id%20desc;$top=2;$skip=1),device_tag'
        .'&$orderby=id%20desc,device_name%20asc&$top=10&$skip=20';

    expect($query->toUrl())->toBe($expected);
    $query->get();
    $this->http->assertSent(fn (Request $request) => $request->url() === $expected
        && $request->method() === 'GET');
});

it('serializes numeric null boolean and date literals and raw expressions', function () {
    expect($this->query->where('id', '>=', 12)->where('temperature', '<', -1.5)
        ->whereNull('note')->where('is_online', false)
        ->where('created_at', new DateTimeImmutable('2026-10-20T10:30:00Z'))
        ->whereRaw("startswith(device_name,'a&b')")
        ->toUrl())->toBe('https://balena.test/v7/device?$filter='
            .'id%20ge%2012%20and%20temperature%20lt%20-1.5%20and%20note%20eq%20null'
            .'%20and%20is_online%20eq%20false'
            ."%20and%20created_at%20eq%20datetime'2026-10-20T10%3A30%3A00Z'"
            ."%20and%20startswith(device_name,'a%26b')");
});

it('keeps count requests as GETs with only a filter', function () {
    expect($this->query->select('id')->whereIn('id', [1, 2, 3])->take(1)->count())->toBe(3);
    $this->http->assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://balena.test/v7/device/$count?$filter=(id%20eq%201%20or%20id%20eq%202%20or%20id%20eq%203)');
});

it('encodes non-expression options and parameter names safely', function () {
    expect($this->client->pineUrl('device', [
        '$select' => '*', '$top' => 0, '$skip' => 0,
        '$format' => 'application/json', 'custom&key' => "a,b='雪'&x=1",
    ]))->toBe('https://balena.test/v7/device?$select=*&$top=0&$skip=0'
        .'&$format=application%2Fjson&custom%26key=a%2Cb%3D%27%E9%9B%AA%27%26x%3D1');
    expect($this->query->toUrl())->toBe('https://balena.test/v7/device');
});

it('preserves mutation methods JSON bodies and filter guards', function () {
    $attributes = ['device_name' => "Roy's & café"];
    $this->query->where('id', 1)->patchMany($attributes);
    $this->query->where('id', 1)->deleteMany();

    $this->http->assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->url() === 'https://balena.test/v7/device?$filter=id%20eq%201'
        && $request->data() === $attributes);
    $this->http->assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://balena.test/v7/device?$filter=id%20eq%201'
        && $request->body() === '');
    expect(fn () => $this->query->patchMany($attributes))->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->query->deleteMany())->toThrow(InvalidArgumentException::class);
    $this->http->assertSentCount(2);
});
