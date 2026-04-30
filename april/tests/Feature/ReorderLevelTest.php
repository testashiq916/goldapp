<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Rotable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderLevelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Item::create([
            'code' => 'TEST001',
            'name' => 'Test Item',
        ]);
    }

    public function test_can_view_reorder_page(): void
    {
        $response = $this->get('/reorder');
        $response->assertStatus(200);
        $response->assertViewIs('reorder.index');
    }

    public function test_can_get_item_details(): void
    {
        $response = $this->postJson('/reorder/get-item', [
            'code' => 'TEST001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'item' => [
                    'code' => 'TEST001',
                    'name' => 'Test Item',
                ],
            ]);
    }

    public function test_cannot_get_nonexistent_item(): void
    {
        $response = $this->postJson('/reorder/get-item', [
            'code' => 'INVALID',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_save_reorder_levels(): void
    {
        $response = $this->postJson('/reorder/save', [
            'code' => 'TEST001',
            'levels' => [
                [
                    'model' => 'MODEL-A',
                    'size' => 'SMALL',
                    'weight1' => 10.5,
                    'weight2' => 50.0,
                    'minqty' => 100,
                    'maxqty' => 500,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('rotable', [
            'code' => 'TEST001',
            'model' => 'MODEL-A',
            'size' => 'SMALL',
        ]);
    }

    public function test_can_delete_all_reorder_levels(): void
    {
        Rotable::create([
            'code' => 'TEST001',
            'model' => 'MODEL-A',
            'size' => 'SMALL',
            'weight1' => 10.5,
            'weight2' => 50.0,
            'minqty' => 100,
            'maxqty' => 500,
        ]);

        $response = $this->postJson('/reorder/delete-all', [
            'code' => 'TEST001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('rotable', [
            'code' => 'TEST001',
        ]);
    }

    public function test_validation_fails_for_invalid_data(): void
    {
        $response = $this->postJson('/reorder/save', [
            'code' => 'TEST001',
            'levels' => [
                [
                    'model' => str_repeat('A', 30),
                    'weight1' => -10,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_can_search_items(): void
    {
        Item::create([
            'code' => 'SEARCH01',
            'name' => 'Searchable Item',
        ]);

        $response = $this->getJson('/reorder/search-items?search=SEARCH');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'code' => 'SEARCH01',
            ]);
    }

    public function test_reorder_levels_are_deleted_when_item_is_deleted(): void
    {
        $item = Item::create([
            'code' => 'DELETE01',
            'name' => 'Item to Delete',
        ]);

        Rotable::create([
            'code' => 'DELETE01',
            'model' => 'MODEL-X',
            'size' => 'LARGE',
            'weight1' => 20,
            'weight2' => 100,
            'minqty' => 50,
            'maxqty' => 200,
        ]);

        $item->delete();

        $this->assertDatabaseMissing('rotable', [
            'code' => 'DELETE01',
        ]);
    }
}

