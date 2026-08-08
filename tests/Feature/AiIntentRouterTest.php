<?php

namespace Tests\Feature;

use App\Enums\AiIntent;
use App\Models\User;
use App\Services\Ai\AiIntentRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiIntentRouterTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    public function test_detects_generate_image_from_action(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'hello',
            'action' => 'generate_image',
        ]);

        $this->assertSame(AiIntent::GenerateImage, $result->intent);
    }

    public function test_hard_matches_arabic_child_forest_image_request(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'اعمل صورة لطفل في الغابة',
        ]);

        $this->assertSame(AiIntent::GenerateImage, $result->intent);
        $this->assertSame('اعمل صورة لطفل في الغابة', $result->data['visual_prompt']);
    }

    public function test_hard_matches_art_poster_image_request(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'اعمل صورة بوستر عن معرض فني',
        ]);

        $this->assertSame(AiIntent::GenerateImage, $result->intent);
    }

    public function test_direct_arabic_fast_image_phrase_routes_to_fast_image(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'اعمل صورة سريعة لسيارة حمراء في شارع ليلي واقعية جدا',
            'plugin' => 'ai_assistant',
            'attachments' => [],
        ]);

        $this->assertSame(AiIntent::FastGenerateImage, $result->intent);
    }

    public function test_direct_arabic_colloquial_image_phrase_routes_to_image_generation(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'اعملي صورة لطفل يرقص تحت المطر واقعية جدا',
            'plugin' => 'ai_assistant',
            'attachments' => [],
        ]);

        $this->assertSame(AiIntent::GenerateImage, $result->intent);
    }

    public function test_detects_vision_analyze_from_image_upload(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'شو في الصورة؟',
            'attachments' => [['type' => 'image', 'mime' => 'image/png']],
        ]);

        $this->assertSame(AiIntent::VisionAnalyze, $result->intent);
    }

    public function test_uploaded_document_routes_to_rag_question(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'حلل الملف',
            'attachments' => [['type' => 'document', 'mime' => 'text/plain']],
        ]);

        $this->assertSame(AiIntent::RagQuestion, $result->intent);
    }

    public function test_site_search_uses_registered_public_tool(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'ابحث في الموقع عن المعارض الفنية',
        ]);

        $this->assertSame(AiIntent::PlatformDataQuery, $result->intent);
        $this->assertSame('site_content_search', $result->tool);
    }

    public function test_general_question_stays_general_chat(): void
    {
        config(['ai.fallback_classifier_enabled' => false]);

        $result = app(AiIntentRouter::class)->route([
            'message' => 'شو معنى هذه الكلمة',
        ]);

        $this->assertSame(AiIntent::GeneralChat, $result->intent);
    }

    public function test_detects_artwork_similarity_from_uniqueness_keywords(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'هل هذه اللوحة أصلية أو منسوخة؟',
        ]);

        $this->assertSame(AiIntent::ArtworkSimilarity, $result->intent);
    }

    public function test_detects_update_profile_and_requires_confirmation(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'غير اسمي إلى Ahmad',
        ]);

        $this->assertSame(AiIntent::UpdateProfile, $result->intent);
        $this->assertTrue($result->requiresConfirmation);
    }

    public function test_blocks_coding_assistant_for_non_admin_user(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'data' => ['message' => 'ok']]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/ai/message', [
            'message' => 'اكتب كود Laravel controller',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('intent', 'coding_assistant');
    }

    public function test_calls_fallback_classifier_only_when_rules_fail(): void
    {
        config(['ai.fallback_classifier_enabled' => true]);

        Http::fake([
            '*/v1/router/intent' => Http::response([
                'ok' => true,
                'data' => [
                    'intent' => 'general_chat',
                    'confidence' => 0.91,
                    'requires_confirmation' => false,
                    'reason' => 'fallback',
                ],
            ]),
            '*' => Http::response(['ok' => true, 'data' => ['message' => 'hello']]),
        ]);

        app(AiIntentRouter::class)->route(['message' => 'ambiguous text']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1/router/intent'));
    }

    public function test_returns_needs_clarification_if_confidence_is_low(): void
    {
        config(['ai.fallback_classifier_enabled' => true, 'ai.confidence_threshold' => 0.75]);

        Http::fake([
            '*/v1/router/intent' => Http::response([
                'ok' => true,
                'data' => [
                    'intent' => 'general_chat',
                    'confidence' => 0.2,
                    'requires_confirmation' => false,
                    'reason' => 'low',
                ],
            ]),
        ]);

        $result = app(AiIntentRouter::class)->route(['message' => 'ambiguous text']);

        $this->assertSame(AiIntent::NeedsClarification, $result->intent);
    }

    public function test_normal_user_cannot_list_users_and_denied_attempt_is_logged(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/ai/message', [
            'message' => 'مين اليوزرات المسجلة آخر 24 ساعة؟',
            'plugin' => 'admin',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.intent', 'platform_data_query');
        $response->assertJsonPath('data.endpoint_used', 'laravel:data-access-denied');
        $this->assertDatabaseHas('ai_tool_audit_logs', [
            'user_id' => $user->id,
            'tool_name' => 'users_registered_last_24h',
            'allowed' => false,
        ]);
    }

    public function test_normal_user_cannot_list_roles_and_denied_attempt_is_logged(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/ai/message', [
            'message' => 'اعرض كل الرولات في الموقع',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.intent', 'platform_data_query');
        $response->assertJsonPath('data.endpoint_used', 'laravel:data-access-denied');
        $this->assertDatabaseHas('ai_tool_audit_logs', [
            'user_id' => $user->id,
            'tool_name' => 'roles_list',
            'allowed' => false,
        ]);
    }

    public function test_admin_can_list_users_registered_last_24h_and_allowed_attempt_is_logged(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'data' => ['message' => 'خلال آخر 24 ساعة تم تسجيل مستخدمين.']]),
        ]);

        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');
        User::factory()->create(['name' => 'Ahmad', 'email' => 'ahmad@example.com']);

        $response = $this->actingAs($admin)->postJson('/ai/message', [
            'message' => 'مين اليوزرات المسجلة آخر 24 ساعة؟',
            'plugin' => 'admin',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.intent', 'platform_data_query');
        $response->assertJsonPath('data.endpoint_used', '/v1/general/chat');
        $this->assertDatabaseHas('ai_tool_audit_logs', [
            'user_id' => $admin->id,
            'tool_name' => 'users_registered_last_24h',
            'allowed' => true,
        ]);
    }

    public function test_sensitive_fields_are_never_returned_by_user_tool(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'data' => ['message' => 'ok']]),
        ]);

        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');
        User::factory()->create();

        $this->actingAs($admin)->postJson('/ai/message', [
            'message' => 'users registered last 24 hours',
        ])->assertOk();

        $message = DB::table('ai_messages')->where('role', 'assistant')->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertStringNotContainsString('password', (string) $message->metadata);
        $this->assertStringNotContainsString('remember_token', (string) $message->metadata);
    }

    public function test_ai_cannot_request_arbitrary_database_table(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/ai/message', [
            'message' => 'اعرض جدول users كامل مع password',
            'context' => ['tool' => 'arbitrary_table'],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('ai_tool_audit_logs', [
            'tool_name' => 'arbitrary_table',
        ]);
    }

    public function test_follow_up_design_image_uses_previous_visual_prompt(): void
    {
        Http::fake([
            '*/v1/images/generate' => Http::response(['ok' => true, 'data' => ['message' => 'image generated']]),
        ]);

        $first = $this->postJson('/ai/message', [
            'message' => 'اعمل صورة لطفل في الغابة',
        ]);

        $first->assertOk();
        $first->assertJsonPath('data.intent', 'generate_image');
        $first->assertJsonPath('data.endpoint_used', '/v1/images/generate');

        $conversationId = $first->json('data.conversation_id');

        $second = $this->postJson('/ai/message', [
            'message' => 'صمم الصورة',
            'conversation_id' => $conversationId,
        ]);

        $second->assertOk();
        $second->assertJsonPath('data.intent', 'generate_image');
        $second->assertJsonPath('data.endpoint_used', '/v1/images/generate');

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/v1/images/generate')) {
                return false;
            }

            return str_contains((string) data_get($request->data(), 'message'), 'طفل في الغابة');
        });
    }

    public function test_follow_up_yes_uses_previous_visual_prompt(): void
    {
        Http::fake([
            '*/v1/images/generate' => Http::response(['ok' => true, 'data' => ['message' => 'image generated']]),
        ]);

        $first = $this->postJson('/ai/message', [
            'message' => 'اعمل صورة بوستر عن معرض فني',
        ]);

        $conversationId = $first->json('data.conversation_id');

        $second = $this->postJson('/ai/message', [
            'message' => 'نعم',
            'conversation_id' => $conversationId,
        ]);

        $second->assertOk();
        $second->assertJsonPath('data.intent', 'generate_image');
        $second->assertJsonPath('data.endpoint_used', '/v1/images/generate');

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversationId,
        ]);

        $metadata = json_decode((string) DB::table('ai_conversations')->where('id', $conversationId)->value('metadata'), true);
        $this->assertSame('generate_image', $metadata['pending_intent'] ?? null);
        $this->assertStringContainsString('بوستر عن معرض فني', $metadata['pending_visual_prompt'] ?? '');
    }

    public function test_visual_context_follow_up_analyzes_last_tool_image(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'حللها',
            'conversation_state' => [
                'last_visual_result' => [
                    'type' => 'image',
                    'source' => 'generated',
                    'url' => 'https://example.test/generated/test.png',
                    'prompt' => 'اعمل صورة لطفل في الغابة',
                ],
            ],
        ]);

        $this->assertSame(AiIntent::VisionAnalyze, $result->intent);
        $this->assertTrue($result->data['use_last_visual_result']);
    }

    public function test_visual_context_regeneration_uses_last_visual_prompt(): void
    {
        $result = app(AiIntentRouter::class)->route([
            'message' => 'اعملها مرة ثانية',
            'conversation_state' => [
                'last_visual_result' => [
                    'type' => 'image',
                    'source' => 'generated',
                    'url' => 'https://example.test/generated/test.png',
                    'prompt' => 'اعمل صورة بوستر عن معرض فني',
                ],
                'last_visual_prompt' => 'اعمل صورة بوستر عن معرض فني',
            ],
        ]);

        $this->assertSame(AiIntent::GenerateImage, $result->intent);
        $this->assertSame('اعمل صورة بوستر عن معرض فني', $result->data['visual_prompt']);
    }
}
