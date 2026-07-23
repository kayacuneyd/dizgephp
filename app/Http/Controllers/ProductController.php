<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Dizge\Data\Database;
use Dizge\Http\Controller;
use Dizge\Http\Request;
use Dizge\Http\Response;
use Dizge\Validation\Validator;

final class ProductController extends Controller
{
    private const RULES = ['name' => 'required|string|max:160'];

    public function __construct(private Database $database) {}

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $rows = $this->database->table('products')->orderBy('id', 'DESC')->limit(50)->get();
        return view('products/index.php', ['title' => 'Products', 'rows' => $rows]);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $row = $this->database->table('products')->where('id', '=', (int) $params['id'])->first();
        if ($row === null) return Response::notFound();
        return view('products/form.php', ['title' => 'Product', 'row' => $row, 'errors' => [], 'readonly' => true]);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        return view('products/form.php', ['title' => 'New Product', 'row' => null, 'errors' => [], 'readonly' => false]);
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params): Response
    {
        try {
            $input = (new Validator())->validate(['name' => (string) $request->input('name')], self::RULES);
        } catch (\Dizge\Validation\ValidationException $exception) {
            return view('products/form.php', ['title' => 'New Product', 'row' => ['name' => $request->input('name')], 'errors' => $exception->errors()->all(), 'readonly' => false]);
        }
        $now = gmdate('c');
        $id = $this->database->table('products')->insert(['name' => $input['name'], 'created_at' => $now, 'updated_at' => $now]);
        return Response::redirect('/products/' . $id);
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): Response
    {
        $row = $this->database->table('products')->where('id', '=', (int) $params['id'])->first();
        if ($row === null) return Response::notFound();
        return view('products/form.php', ['title' => 'Edit Product', 'row' => $row, 'errors' => [], 'readonly' => false]);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        try {
            $input = (new Validator())->validate(['name' => (string) $request->input('name')], self::RULES);
        } catch (\Dizge\Validation\ValidationException $exception) {
            $row = ['id' => $params['id'], 'name' => $request->input('name')];
            return view('products/form.php', ['title' => 'Edit Product', 'row' => $row, 'errors' => $exception->errors()->all(), 'readonly' => false]);
        }
        $updated = $this->database->table('products')->where('id', '=', (int) $params['id'])->update(['name' => $input['name'], 'updated_at' => gmdate('c')]);
        if ($updated === 0) return Response::notFound();
        return Response::redirect('/products/' . $params['id']);
    }

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params): Response
    {
        $deleted = $this->database->table('products')->where('id', '=', (int) $params['id'])->delete();
        if ($deleted === 0) return Response::notFound();
        return Response::redirect('/products');
    }
}
