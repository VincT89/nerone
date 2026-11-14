<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Musical<br>Experience', false)
            ->assertSee('NERO!')
            ->assertSee('NERO! è il nuovo musical in lingua inglese dedicato a Nerone e alla Roma del grande incendio.')
            ->assertSee('Uno spettacolo originale, in preparazione a Roma, che racconta l’uomo, l’imperatore e il mito attraverso la musica e il teatro.')
            ->assertSee('Le candidature per le audizioni si presentano esclusivamente online, compilando il')
            ->assertSeeInOrder(['Direzione tecnica', 'Direzione artistica', 'Laccio e Ernesto Migliacci'])
            ->assertSee('href="mailto:info@neromusical.com"', false);

        $this->get('/candidati')->assertOk()
            ->assertSee('Audizione online per il cast di NERO!')
            ->assertSee('href="mailto:info@neromusical.com"', false);
    }
}
