import { Component, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css'
})
export class HomeComponent {
  currentTime = signal(new Date());
  currentUser = computed(() => this.authService.currentUser());

  constructor(private authService: AuthService) {
    // Actualizar la hora cada minuto
    setInterval(() => {
      this.currentTime.set(new Date());
    }, 60000);
  }

  getGreeting(): string {
    const hour = this.currentTime().getHours();
    if (hour < 12) {
      return 'Buenos días';
    } else if (hour < 18) {
      return 'Buenas tardes';
    } else {
      return 'Buenas noches';
    }
  }

  formatTime(): string {
    return this.currentTime().toLocaleTimeString('es-ES', {
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  formatDate(): string {
    return this.currentTime().toLocaleDateString('es-ES', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }
}
