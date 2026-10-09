#!/usr/bin/env node

/**
 * Mixpost AI Auto-Poster CLI & Automation Script
 *
 * Can be run locally, via CRON, or in GitHub Actions to auto-generate
 * posts using AI (Gemini / OpenAI / Claude) and schedule them into Mixpost.
 */

require('dotenv').config();
const { Client } = require('pg');

const AI_PROVIDER = process.env.AI_PROVIDER || 'gemini';
const GEMINI_API_KEY = process.env.GEMINI_API_KEY;
const OPENAI_API_KEY = process.env.OPENAI_API_KEY;
const ANTHROPIC_API_KEY = process.env.ANTHROPIC_API_KEY;

const TOPIC = process.argv[2] || process.env.AI_POSTING_TOPICS || 'Latest trends in Artificial Intelligence, web development, and tech productivity';
const TONE = process.env.AI_POSTING_TONE || 'insightful, engaging, and professional with hashtags';
const SCHEDULE_HOURS = parseInt(process.argv[3] || '2', 10);

async function generateWithGemini(prompt) {
    const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${GEMINI_API_KEY}`;
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            contents: [{ parts: [{ text: prompt }] }],
            generationConfig: { responseMimeType: 'application/json' }
        })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(JSON.stringify(data));
    return JSON.parse(data.candidates[0].content.parts[0].text);
}

async function generateWithOpenAI(prompt) {
    const res = await fetch('https://api.openai.com/v1/chat/completions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${OPENAI_API_KEY}`
        },
        body: JSON.stringify({
            model: process.env.OPENAI_MODEL || 'gpt-4o-mini',
            messages: [
                { role: 'system', content: 'You are an elite social media manager. Output valid JSON.' },
                { role: 'user', content: prompt }
            ],
            response_format: { type: 'json_object' }
        })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(JSON.stringify(data));
    return JSON.parse(data.choices[0].message.content);
}

async function generateWithClaude(prompt) {
    const res = await fetch('https://api.anthropic.com/v1/messages', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'x-api-key': ANTHROPIC_API_KEY,
            'anthropic-version': '2023-06-01'
        },
        body: JSON.stringify({
            model: process.env.CLAUDE_MODEL || 'claude-3-5-haiku-latest',
            max_tokens: 1024,
            messages: [{ role: 'user', content: prompt }]
        })
    });
    const data = await res.json();
    if (!res.ok) throw new Error(JSON.stringify(data));
    const text = data.content[0].text.replace(/```json\s*|\s*```/g, '').trim();
    return JSON.parse(text);
}

async function main() {
    console.log(`🤖 Mixpost AI Content Generator (${AI_PROVIDER.toUpperCase()})`);
    console.log(`📌 Topic: "${TOPIC}"`);
    console.log(`🎯 Tone: "${TONE}"`);

    const prompt = `You are an expert social media strategist.
Create an engaging social media post about: "${TOPIC}".
Tone: ${TONE}.

Return a strictly valid JSON object:
{
  "text": "The main social media post copy including engaging hook, body, and relevant hashtags (under 280 chars or general version)",
  "image_prompt": "A descriptive prompt for an eye-catching 4k background image"
}`;

    let result;
    if (AI_PROVIDER === 'openai' && OPENAI_API_KEY) {
        result = await generateWithOpenAI(prompt);
    } else if (AI_PROVIDER === 'claude' && ANTHROPIC_API_KEY) {
        result = await generateWithClaude(prompt);
    } else if (GEMINI_API_KEY) {
        result = await generateWithGemini(prompt);
    } else {
        console.error('❌ Error: No AI API Key found. Set GEMINI_API_KEY, OPENAI_API_KEY, or ANTHROPIC_API_KEY in .env');
        process.exit(1);
    }

    console.log('\n✅ Post Generated Successfully:');
    console.log('----------------------------------------------------');
    console.log(result.text);
    console.log('----------------------------------------------------');

    const imagePrompt = encodeURIComponent(`${result.image_prompt || TOPIC} 4k digital photography wallpaper clean`);
    const imageUrl = `https://image.pollinations.ai/prompt/${imagePrompt}?width=1200&height=630&nologo=true`;
    console.log(`🖼️ Image URL: ${imageUrl}\n`);

    // Check if we can connect to Supabase PostgreSQL database
    if (process.env.DB_HOST && process.env.DB_PASSWORD) {
        console.log('🔌 Connecting to Supabase PostgreSQL database...');
        const client = new Client({
            host: process.env.DB_HOST,
            port: process.env.DB_PORT || 5432,
            database: process.env.DB_DATABASE || 'postgres',
            user: process.env.DB_USERNAME || 'postgres',
            password: process.env.DB_PASSWORD,
            ssl: { rejectUnauthorized: false }
        });

        try {
            await client.connect();
            const accounts = await client.query('SELECT id, name FROM mixpost_accounts');

            if (accounts.rows.length === 0) {
                console.log('⚠️ No social accounts connected yet in Mixpost. Log into your Mixpost UI to connect accounts.');
                await client.end();
                return;
            }

            const scheduleDate = new Date(Date.now() + SCHEDULE_HOURS * 60 * 60 * 1000);

            const postRes = await client.query(
                `INSERT INTO mixpost_posts (status, scheduled_at, created_at, updated_at)
                 VALUES ($1, $2, NOW(), NOW()) RETURNING id`,
                ['SCHEDULED', scheduleDate]
            );

            const postId = postRes.rows[0].id;

            const postContent = JSON.stringify([
                { type: 'text', data: { text: result.text } }
            ]);

            await client.query(
                `INSERT INTO mixpost_post_versions (post_id, account_id, is_original, content, created_at, updated_at)
                 VALUES ($1, 0, 1, $2, NOW(), NOW())`,
                [postId, postContent]
            );

            for (const acc of accounts.rows) {
                await client.query(
                    `INSERT INTO mixpost_post_accounts (post_id, account_id, created_at, updated_at)
                     VALUES ($1, $2, NOW(), NOW())`,
                    [postId, acc.id]
                );
            }

            console.log(`🎉 Post ID #${postId} successfully scheduled for ${scheduleDate.toISOString()} across ${accounts.rows.length} accounts!`);
            await client.end();
        } catch (err) {
            console.error('Database insertion error:', err.message);
        }
    } else {
        console.log('💡 Note: Set DB_HOST and DB_PASSWORD in .env to automatically insert and schedule posts into your database.');
    }
}

main().catch(err => {
    console.error('Fatal Error:', err);
    process.exit(1);
});
