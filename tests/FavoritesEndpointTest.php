<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\FavoritesEndpoint;

class FavoritesEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$user_meta = [];
        Stubs::$logged_in = true;
        Stubs::$posts = [];
        Stubs::$last_query_args = [];
        Stubs::$meta = [];
    }

    public function test_add_favorite_adds_id()
    {
        $endpoint = new FavoritesEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('addFavorite');
        $method->setAccessible(true);

        $request = new WP_REST_Request();
        $request->set_param('post_id', 42);

        $response = $method->invoke($endpoint, $request);

        $this->assertSame([42], Stubs::$user_meta['ead_favorites']);
        $this->assertSame([42], $response->data['favorites']);
    }

    public function test_remove_favorite_removes_id()
    {
        Stubs::$user_meta = ['ead_favorites' => [42, 7]];

        $endpoint = new FavoritesEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('removeFavorite');
        $method->setAccessible(true);

        $request = new WP_REST_Request();
        $request->set_param('post_id', 42);

        $response = $method->invoke($endpoint, $request);

        $this->assertSame([7], Stubs::$user_meta['ead_favorites']);
        $this->assertSame([7], $response->data['favorites']);
    }

    public function test_get_favorites_returns_event_data()
    {
        Stubs::$user_meta = ['ead_favorites' => [10]];
        Stubs::$posts = [(object) ['ID' => 10, 'post_title' => 'Fav']];
        Stubs::$meta = [10 => [
            'event_city' => 'Paris',
            'event_state' => 'Ile',
            'event_country' => 'France',
        ]];

        $endpoint = new FavoritesEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('getFavorites');
        $method->setAccessible(true);

        $request = new WP_REST_Request();

        $response = $method->invoke($endpoint, $request);

        $this->assertSame('Fav', $response->data[0]['title']);
        $this->assertSame('Paris', $response->data[0]['venue']['city']);
        $this->assertSame([10], Stubs::$last_query_args['post__in']);
    }
}
