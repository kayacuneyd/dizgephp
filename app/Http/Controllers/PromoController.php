<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Dizge\Http\Controller;
use Dizge\Http\Request;
use Dizge\Http\Response;

final class PromoController extends Controller
{
    /**
     * @param array<array{slug:string,title:string,description:string}> $docs
     * @param array<array{slug:string,title:string,description:string}> $tutorials
     */
    public function __construct(private array $docs, private array $tutorials) {}

    /** @param array<string,string> $params */
    public function home(Request $request, array $params): Response
    {
        return view('promo/home.php', []);
    }

    /** @param array<string,string> $params */
    public function docs(Request $request, array $params): Response
    {
        $index = array_map(fn (array $d) => ['slug' => $d['slug'], 'title' => $d['title'], 'description' => $d['description']], $this->docs);
        return view('promo/docs.php', ['docs' => $index]);
    }

    /** @param array<string,string> $params */
    public function docDetail(Request $request, array $params): Response
    {
        $slug = $params['slug'];
        foreach ($this->docs as $doc) {
            if ($doc['slug'] === $slug) return view('promo/doc-detail.php', ['doc' => $doc]);
        }
        return Response::notFound();
    }

    /** @param array<string,string> $params */
    public function tutorials(Request $request, array $params): Response
    {
        $index = array_map(fn (array $t) => ['slug' => $t['slug'], 'title' => $t['title'], 'description' => $t['description']], $this->tutorials);
        return view('promo/tutorials.php', ['tutorials' => $index]);
    }

    /** @param array<string,string> $params */
    public function tutorialDetail(Request $request, array $params): Response
    {
        $slug = $params['slug'];
        foreach ($this->tutorials as $tutorial) {
            if ($tutorial['slug'] === $slug) return view('promo/tutorial-detail.php', ['tutorial' => $tutorial]);
        }
        return Response::notFound();
    }

    /** @param array<string,string> $params */
    public function download(Request $request, array $params): Response
    {
        return view('promo/download.php', []);
    }

    /** @param array<string,string> $params */ public function features(Request $request, array $params): Response { return view('promo/features.php', []); }
    /** @param array<string,string> $params */ public function headless(Request $request, array $params): Response { return view('promo/headless.php', []); }
    /** @param array<string,string> $params */ public function installGuide(Request $request, array $params): Response { return view('promo/install-guide.php', []); }
    /** @param array<string,string> $params */ public function migrateWordpress(Request $request, array $params): Response { return view('promo/migrate-wordpress.php', []); }

    /** @param array<string,string> $params */
    public function about(Request $request, array $params): Response
    {
        return view('promo/about.php', []);
    }
}
