<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class UserModelTest extends TestCase
{
    #[Test]
    public function it_has_correct_fillable_attributes(): void
    {
        $user = new User();
        $fillable = $user->getFillable();
        
        $expectedFillable = [
            'name',
            'email',
            'password',
            'email_verified_at',
            'fecha_nacimiento',
            'pais',
            'genero',
            'telefono',
            'activo',
            'last_login',
        ];
        
        $this->assertEquals($expectedFillable, $fillable);
    }

    #[Test]
    public function it_has_correct_hidden_attributes(): void
    {
        $user = new User();
        $hidden = $user->getHidden();
        
        $expectedHidden = [
            'password',
            'remember_token',
        ];
        
        $this->assertEquals($expectedHidden, $hidden);
    }

    #[Test]
    public function it_can_set_and_get_attributes(): void
    {
        $user = new User();
        
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->pais = 'Ecuador';
        $user->genero = 'M';
        $user->telefono = '0999999999';
        $user->activo = true;
        
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('Ecuador', $user->pais);
        $this->assertEquals('M', $user->genero);
        $this->assertEquals('0999999999', $user->telefono);
        $this->assertTrue($user->activo);
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $user = new User();
        $this->assertEquals('users', $user->getTable());
    }
}
