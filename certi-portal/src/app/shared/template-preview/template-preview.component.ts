import { Component, Input, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';

export interface TemplatePreviewData {
  id: number;
  name: string;
  file_url?: string;
  qr_position?: any;
  name_position?: any;
  date_position?: any;
  background_image_size?: any;
  template_styles?: any;
}

@Component({
  selector: 'app-template-preview',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="template-preview-container" [style.width.px]="containerWidth()" [style.height.px]="containerHeight()">
      <div class="image-size-info">
        <small>
          Render: {{ imageWidth() }}×{{ imageHeight() }} px
          | Base: {{ template?.background_image_size?.width || naturalW() }}×{{ template?.background_image_size?.height || naturalH() }} px
        </small>
      </div>
      <div class="template-preview-wrapper" *ngIf="template?.file_url" [style.width.px]="imageWidth()" [style.height.px]="imageHeight()">
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
          *ngIf="template?.qr_position && showElements && qrUrl && imageLoaded()"
          class="qr-element"
          [style.left.px]="getQrLeft()"
          [style.top.px]="getQrTop()"
          [style.width.px]="getQrSize()"
          [style.height.px]="getQrSize()"
          [style.transform]="'rotate(' + getQrRotation() + 'deg)'"
        >
          <img [src]="qrUrl" alt="QR" [style.width.px]="getQrSize()" [style.height.px]="getQrSize()" />
        </div>

        <!-- Elemento Nombre -->
        <div
          *ngIf="template?.name_position && showElements && imageLoaded()"
          class="name-element"
          [style.left.px]="getNameLeft()"
          [style.top.px]="getNameTop()"
          [style.font-size.px]="getNameFontSize()"
          [style.color]="getNameColor()"
          [style.font-family]="getNameFontFamily()"
          [style.transform]="'rotate(' + getNameRotation() + 'deg)'"
        >
          {{ getNameText() }}
        </div>

        <!-- Elemento Fecha -->
        <div
          *ngIf="template?.date_position && showElements && imageLoaded()"
          class="date-element"
          [style.left.px]="getDateLeft()"
          [style.top.px]="getDateTop()"
          [style.font-size.px]="getDateFontSize()"
          [style.color]="getDateColor()"
          [style.font-family]="getDateFontFamily()"
          [style.transform]="'rotate(' + getDateRotation() + 'deg)'"
        >
          {{ getDateText() }}
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
      align-items: flex-start;
      justify-content: center;
    }

    .image-size-info {
      position: absolute;
      top: 4px;
      left: 8px;
      color: #6c757d;
      font-size: 12px;
      z-index: 10;
    }

    .template-preview-wrapper {
      position: relative;
      display: inline-block;
      margin-top: 18px;
    }

    .template-background-image {
      display: block;
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      position: relative;
      z-index: 0;
    }

    .qr-element { position: absolute; z-index: 5; transform-origin: top left; }


    .name-element { position: absolute; white-space: nowrap; z-index: 5; transform-origin: top left; }

    .date-element { position: absolute; white-space: nowrap; z-index: 5; transform-origin: top left; }

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
  @Input() sampleDate: string = '01/01/2024';
  @Input() qrUrl: string | null = null;

  containerWidth = signal(300);
  containerHeight = signal(200);
  imageWidth = signal(0);
  imageHeight = signal(0);
  imageLoaded = signal(false);
  naturalW = signal(0);
  naturalH = signal(0);

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
    this.naturalW.set(naturalWidth);
    this.naturalH.set(naturalHeight);
    this.imageLoaded.set(true);
  }

  getQrLeft(): number {
    if (!this.template?.qr_position || !this.imageLoaded()) return 0;
    const scaleX = this.getScaleXNatural();
    const baseW = this.getBaseWidth();
    const origin = this.getCoordsOrigin();
    const hasLeft = this.template.qr_position.left != null;
    const leftVal = Number(this.template.qr_position.left ?? 0);
    const xVal = Number(this.template.qr_position.x ?? 0);
    const posX = hasLeft ? leftVal : (origin === 'center' ? (xVal + (baseW / 2)) : xVal);
    return posX * scaleX;
  }

  getQrTop(): number {
    if (!this.template?.qr_position || !this.imageLoaded()) return 0;
    const scaleY = this.getScaleYNatural();
    const baseH = this.getBaseHeight();
    const origin = this.getCoordsOrigin();
    const hasTop = this.template.qr_position.top != null;
    const topVal = Number(this.template.qr_position.top ?? 0);
    const yVal = Number(this.template.qr_position.y ?? 0);
    const posY = hasTop ? topVal : (origin === 'center' ? (yVal + (baseH / 2)) : yVal);
    return posY * scaleY;
  }

  getQrSize(): number {
    if (!this.template?.qr_position || !this.imageLoaded()) return 50;
    const size = Number(this.template.qr_position.width || this.template.qr_position.size || 80);
    const scaleX = this.getScaleXNatural();
    return size * scaleX;
  }

  getQrRotation(): number {
    return Number(this.template?.qr_position?.rotation || 0);
  }

  getNameLeft(): number {
    if (!this.template?.name_position || !this.imageLoaded()) return 0;
    const scaleX = this.getScaleXNatural();
    const baseW = this.getBaseWidth();
    const origin = this.getCoordsOrigin();
    const hasLeft = this.template.name_position.left != null;
    const leftVal = Number(this.template.name_position.left ?? 0);
    const xVal = Number(this.template.name_position.x ?? 0);
    const posX = hasLeft ? leftVal : (origin === 'center' ? (xVal + (baseW / 2)) : xVal);
    return posX * scaleX;
  }

  getNameTop(): number {
    if (!this.template?.name_position || !this.imageLoaded()) return 0;
    const scaleY = this.getScaleYNatural();
    const baseH = this.getBaseHeight();
    const origin = this.getCoordsOrigin();
    const hasTop = this.template.name_position.top != null;
    const topVal = Number(this.template.name_position.top ?? 0);
    const yVal = Number(this.template.name_position.y ?? 0);
    const posY = hasTop ? topVal : (origin === 'center' ? (yVal + (baseH / 2)) : yVal);
    return posY * scaleY;
  }

  getNameFontSize(): number {
    if (!this.template?.name_position || !this.imageLoaded()) return 16;
    const size = Number(this.template.name_position.fontSize || 28);
    const scale = this.getScaleXNatural();
    return size * scale;
  }

  // Sin recorte: se muestran posiciones tal cual, usando escala basada en background_image_size

  getNameColor(): string {
    const np = this.template?.name_position;
    return (np?.color ?? np?.fill ?? '#000000');
  }

  getNameFontFamily(): string {
    return this.template?.name_position?.fontFamily || 'Arial';
  }

  getNameText(): string {
    return this.sampleUserName;
  }

  getDateLeft(): number {
    if (!this.template?.date_position || !this.imageLoaded()) return 0;
    const scaleX = this.getScaleXNatural();
    const baseW = this.getBaseWidth();
    const origin = this.getCoordsOrigin();
    const hasLeft = this.template.date_position.left != null;
    const leftVal = Number(this.template.date_position.left ?? 0);
    const xVal = Number(this.template.date_position.x ?? 0);
    const posX = hasLeft ? leftVal : (origin === 'center' ? (xVal + (baseW / 2)) : xVal);
    return posX * scaleX;
  }

  getDateTop(): number {
    if (!this.template?.date_position || !this.imageLoaded()) return 0;
    const scaleY = this.getScaleYNatural();
    const baseH = this.getBaseHeight();
    const origin = this.getCoordsOrigin();
    const hasTop = this.template.date_position.top != null;
    const topVal = Number(this.template.date_position.top ?? 0);
    const yVal = Number(this.template.date_position.y ?? 0);
    const posY = hasTop ? topVal : (origin === 'center' ? (yVal + (baseH / 2)) : yVal);
    return posY * scaleY;
  }

  getDateFontSize(): number {
    if (!this.template?.date_position || !this.imageLoaded()) return 14;
    const size = Number(this.template.date_position.fontSize || 16);
    const scale = this.getScaleXNatural();
    return size * scale;
  }

  getDateColor(): string {
    const dp = this.template?.date_position;
    return (dp?.color ?? dp?.fill ?? '#333333');
  }

  getDateFontFamily(): string {
    return this.template?.date_position?.fontFamily || 'Arial';
  }

  getDateText(): string {
    return this.sampleDate;
  }

  private getScaleXNatural(): number {
    if (!this.imageLoaded()) return 1;
    const base = Number(this.template?.background_image_size?.width || this.naturalW() || this.imageWidth());
    return base ? this.imageWidth() / base : 1;
  }

  private getScaleYNatural(): number {
    if (!this.imageLoaded()) return 1;
    const base = Number(this.template?.background_image_size?.height || this.naturalH() || this.imageHeight());
    return base ? this.imageHeight() / base : 1;
  }

  private getBaseWidth(): number {
    return Number(this.template?.background_image_size?.width || this.naturalW() || this.imageWidth());
  }

  private getBaseHeight(): number {
    return Number(this.template?.background_image_size?.height || this.naturalH() || this.imageHeight());
  }

  private getCoordsOrigin(): 'center' | 'left-top' {
    const origin = String(this.template?.template_styles?.coords_origin || 'center').toLowerCase();
    return origin === 'left-top' ? 'left-top' : 'center';
  }

  getNameRotation(): number {
    return Number(this.template?.name_position?.rotation || 0);
  }

  getDateRotation(): number {
    return Number(this.template?.date_position?.rotation || 0);
  }
}
