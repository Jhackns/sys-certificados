import { Component, Input, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';

export interface TemplatePreviewData {
  id: number;
  name: string;
  file_url?: string;
  qr_position?: any;
  name_position?: any;
  background_image_size?: any;
}

@Component({
  selector: 'app-template-preview',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="template-preview-container" [style.width.px]="containerWidth()" [style.height.px]="containerHeight()">
      <div class="template-preview-wrapper" *ngIf="template?.file_url">
        <!-- Imagen de fondo -->
        <img
          [src]="template?.file_url"
          [alt]="template?.name"
          class="template-background-image"
          (load)="onImageLoad($event)"
          [style.width.px]="imageWidth()"
          [style.height.px]="imageHeight()"
        >

        <!-- Elemento QR -->
        <div
          *ngIf="template?.qr_position && showElements"
          class="qr-element"
          [style.left.px]="getQrLeft()"
          [style.top.px]="getQrTop()"
          [style.width.px]="getQrSize()"
          [style.height.px]="getQrSize()"
        >
          <div class="qr-placeholder">
            <i class="fas fa-qrcode"></i>
            <span>QR</span>
          </div>
        </div>

        <!-- Elemento Nombre -->
        <div
          *ngIf="template?.name_position && showElements"
          class="name-element"
          [style.left.px]="getNameLeft()"
          [style.top.px]="getNameTop()"
          [style.font-size.px]="getNameFontSize()"
          [style.color]="getNameColor()"
          [style.font-family]="getNameFontFamily()"
        >
          {{ getNameText() }}
        </div>
      </div>

      <!-- Mensaje cuando no hay imagen -->
      <div *ngIf="!template?.file_url" class="no-image-placeholder">
        <i class="fas fa-image"></i>
        <span>Sin imagen</span>
      </div>
    </div>
  `,
  styles: [`
    .template-preview-container {
      position: relative;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      overflow: hidden;
      background: #f8f9fa;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .template-preview-wrapper {
      position: relative;
      display: inline-block;
    }

    .template-background-image {
      display: block;
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }

    .qr-element {
      position: absolute;
      border: 2px solid #007bff;
      background: rgba(0, 123, 255, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 4px;
    }

    .qr-placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #007bff;
      font-size: 12px;
      font-weight: bold;
    }

    .qr-placeholder i {
      font-size: 16px;
      margin-bottom: 2px;
    }

    .name-element {
      position: absolute;
      border: 2px solid #28a745;
      background: rgba(40, 167, 69, 0.1);
      padding: 4px 8px;
      border-radius: 4px;
      font-weight: bold;
      white-space: nowrap;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .no-image-placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #6c757d;
      font-size: 14px;
      height: 100%;
      min-height: 150px;
    }

    .no-image-placeholder i {
      font-size: 24px;
      margin-bottom: 8px;
    }
  `]
})
export class TemplatePreviewComponent implements OnInit {
  @Input() template: TemplatePreviewData | null = null;
  @Input() maxWidth: number = 300;
  @Input() maxHeight: number = 200;
  @Input() showElements: boolean = true;
  @Input() sampleUserName: string = 'Juan Pérez';

  containerWidth = signal(300);
  containerHeight = signal(200);
  imageWidth = signal(0);
  imageHeight = signal(0);
  imageLoaded = signal(false);

  ngOnInit() {
    this.containerWidth.set(this.maxWidth);
    this.containerHeight.set(this.maxHeight);
  }

  onImageLoad(event: Event) {
    const img = event.target as HTMLImageElement;
    const naturalWidth = img.naturalWidth;
    const naturalHeight = img.naturalHeight;

    // Calcular dimensiones manteniendo aspect ratio
    const aspectRatio = naturalWidth / naturalHeight;
    let width = this.maxWidth;
    let height = this.maxHeight;

    if (aspectRatio > this.maxWidth / this.maxHeight) {
      // La imagen es más ancha
      height = width / aspectRatio;
    } else {
      // La imagen es más alta
      width = height * aspectRatio;
    }

    this.imageWidth.set(width);
    this.imageHeight.set(height);
    this.containerWidth.set(width);
    this.containerHeight.set(height);
    this.imageLoaded.set(true);
  }

  getQrLeft(): number {
    if (!this.template?.qr_position || !this.imageLoaded()) return 0;

    const scaleX = this.getScaleX();
    return (this.template.qr_position.left || 0) * scaleX;
  }

  getQrTop(): number {
    if (!this.template?.qr_position || !this.imageLoaded()) return 0;

    const scaleY = this.getScaleY();
    return (this.template.qr_position.top || 0) * scaleY;
  }

  getQrSize(): number {
    if (!this.template?.qr_position || !this.imageLoaded()) return 50;

    const scale = Math.min(this.getScaleX(), this.getScaleY());
    return (this.template.qr_position.width || 80) * scale;
  }

  getNameLeft(): number {
    if (!this.template?.name_position || !this.imageLoaded()) return 0;

    const scaleX = this.getScaleX();
    return (this.template.name_position.left || 0) * scaleX;
  }

  getNameTop(): number {
    if (!this.template?.name_position || !this.imageLoaded()) return 0;

    const scaleY = this.getScaleY();
    return (this.template.name_position.top || 0) * scaleY;
  }

  getNameFontSize(): number {
    if (!this.template?.name_position || !this.imageLoaded()) return 16;

    const scale = Math.min(this.getScaleX(), this.getScaleY());
    return (this.template.name_position.fontSize || 24) * scale;
  }

  getNameColor(): string {
    return this.template?.name_position?.fill || '#000000';
  }

  getNameFontFamily(): string {
    return this.template?.name_position?.fontFamily || 'Arial';
  }

  getNameText(): string {
    return this.sampleUserName;
  }

  private getScaleX(): number {
    if (!this.template?.background_image_size || !this.imageLoaded()) return 1;
    return this.imageWidth() / (this.template.background_image_size.width || this.imageWidth());
  }

  private getScaleY(): number {
    if (!this.template?.background_image_size || !this.imageLoaded()) return 1;
    return this.imageHeight() / (this.template.background_image_size.height || this.imageHeight());
  }
}
