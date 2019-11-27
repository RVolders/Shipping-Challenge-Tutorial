<?php

namespace App\Http\Controllers;

use App\Genre;
use App\Record;
use Illuminate\Http\Request;
use Json;

class ShopController extends Controller
{
    // Master Page: http://vinyl_shop.test/shop or http://localhost:3000/shop
    public function index(Request $request)
    {
        //Shorter version (with null coalescing operator)
        $genre_id = $request->input('genre_id') ?? '%'; //OR $genre_id = $request->genre_id ?? '%';
        $artist_title = '%' . $request->input('artist') . '%'; //OR $artist_title = '%' . $request->artist . '%';
        $records = Record::with('genre')->orderBy('artist')
            //Advanced search
            ->where(function ($query) use ($artist_title, $genre_id) {
                $query->where('artist', 'like', $artist_title)
                    ->where('genre_id', 'like', $genre_id);
            })
            ->orWhere(function ($query) use ($artist_title, $genre_id) {
                $query->where('title', 'like', $artist_title)
                    ->where('genre_id', 'like', $genre_id);
            })
            ->paginate(12) //get 12perPage
            ->appends(['artist' => $request->input('artist'), 'genre_id' => $request->input('genre_id')]);
        //OR ->appends(['artist' => $request->artist, 'genre_id' => $request->genre_id]);

        //STANDAARD -> $records = Record::with('genre')->get(); // get all records
        foreach ($records as $record) {
            $record->cover = $record->cover ?? "https://coverartarchive.org/release/$record->title_mbid/front-250.jpg";
        }

        // ADD A SEARCH FORM
        $genres = Genre::orderBy('name') // short version of orderBy('name', 'asc')
        ->has('records')        // only genres that have one or more records
        ->withCount('records')  // add a new property 'records_count' to the Genre models/objects
        ->get()
            ->transform(function ($item, $key) {
                // Set first letter of name to uppercase and add the counter
                $item->name = ucfirst($item->name) . ' (' . $item->records_count . ')';
                // Remove all fields that you don't use inside the view
                unset($item->created_at, $item->updated_at, $item->records_count);
                return $item;
            });

        $result = compact('genres', 'records');     // $result = ['genres' => $genres, 'records' => $records]

        Json::dump($result);                    // open http://vinyl_shop.test/shop?json
        return view('shop.index', $result);
    }

    // Detail Page: http://vinyl_shop.test/shop/{id} or http://localhost:3000/shop/{id}
    public function show($id)
    {
        //Shop: detail page
        $record = Record::with('genre')->findOrFail($id);
        // dd($record);
        // Real path to cover image
        $record->cover = $record->cover ?? "https://coverartarchive.org/release/$record->title_mbid/front-250.jpg";
        // Combine artist + title
        $record->title = $record->artist . ' - ' . $record->title;
        // Links to MusicBrainz API (used by jQuery)
        // https://wiki.musicbrainz.org/Development/JSON_Web_Service
        $record->artistUrl = 'https://musicbrainz.org/ws/2/artist/' . $record->artist_mbid . '?inc=url-rels&fmt=json';
        $record->recordUrl = 'https://musicbrainz.org/ws/2/release/' . $record->title_mbid . '?inc=recordings+url-rels&fmt=json';
        // If stock > 0: button is green, otherwise the button is red
        $record->btnClass = $record->stock > 0 ? 'btn-outline-success' : 'btn-outline-danger';
        // You can't overwrite the attribute genre (object) with a string, so we make a new attribute
        $record->genreName = $record->genre->name;
        // Remove attributes you don't need for the view
        unset($record->genre_id, $record->artist, $record->created_at, $record->updated_at, $record->artist_mbid, $record->title_mbid, $record->genre);
        $result = compact('record');
        Json::dump($result);
        return view('shop.show', $result);  // Pass $result to the view


        //OUD
        // return view('shop.show', ['id' => $id]);  // Send $id to the view
    }


}