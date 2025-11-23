import { Component, Input, Output, EventEmitter, ViewChild, ElementRef, OnInit, OnDestroy, AfterViewInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Canvas, FabricImage, FabricText, Rect, Point } from 'fabric';
import { TemplateService } from '../../core/services/template.service';

export interface ElementPosition {
  x: number;
  y: number;
  width: number;
  height: number;
  fontSize?: number;
  fontFamily?: string;
}

export interface PositionData {
  qr_position?: ElementPosition;
  name_position?: ElementPosition;
}

@Component({
  selector: 'app-position-editor',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './position-editor.html',
  styleUrls: ['./position-editor.css']
})
export class PositionEditorComponent implements OnInit, OnDestroy, AfterViewInit {
  @ViewChild('canvasElement', { static: false }) canvasElement!: ElementRef<HTMLCanvasElement>;

  @Input() imageUrl: string = '';
  @Input() templateId: number = 0;
  @Input() qrPosition: any = null;
  @Input() namePosition: any = null;
  @Output() positionsChanged = new EventEmitter<{ qrPosition: any; namePosition: any }>();
  @Output() closed = new EventEmitter<void>();

  private canvas!: Canvas;
  qrElement?: Rect;
  nameElement?: FabricText;
  private backgroundImage?: FabricImage;
  private originalImageSize?: { width: number; height: number };

  private templateService = inject(TemplateService);

  isLoading = signal(false);
  selectedTool = signal<'qr' | 'name' | null>(null);

  // Configuraciones por defecto
  private readonly QR_SIZE = 80;
  private readonly DEFAULT_FONT_SIZE = 24;
  private readonly DEFAULT_FONT_FAMILY = 'Arial';

  ngOnInit(): void {
    // Inicializar después de que la vista esté lista
  }

  ngAfterViewInit(): void {
    // Inicializar canvas después de que la vista esté completamente cargada
    setTimeout(() => {
      this.initializeCanvas();
    }, 100);
  }

  ngOnDestroy(): void {
    if (this.canvas) {
      // Limpiar event listeners personalizados
      const canvasElement = this.canvasElement?.nativeElement;
      if (canvasElement && (canvasElement as any)._customWheelHandler) {
        canvasElement.removeEventListener('wheel', (canvasElement as any)._customWheelHandler);
      }

      this.canvas.dispose();
    }
  }

  private async initializeCanvas(): Promise<void> {
    // Verificar que el elemento canvas esté disponible
    if (!this.canvasElement?.nativeElement) {
      console.error('Canvas element not found');
      return;
    }

    this.isLoading.set(true);

    // Primero cargar la imagen para obtener sus dimensiones
    let canvasWidth = 800;
    let canvasHeight = 600;

    if (this.imageUrl) {
      try {
        // Cargar imagen temporalmente para obtener dimensiones
        const tempImg = await FabricImage.fromURL(this.imageUrl, {}, {});
        if (tempImg && tempImg.getElement()) {
          // Guardar las dimensiones originales de la imagen
          this.originalImageSize = {
            width: tempImg.width!,
            height: tempImg.height!
          };

          // Obtener dimensiones del contenedor
          const containerElement = this.canvasElement.nativeElement.parentElement;
          const containerWidth = containerElement?.clientWidth || window.innerWidth * 0.8;
          const containerHeight = containerElement?.clientHeight || window.innerHeight * 0.7;

          // Calcular el tamaño máximo disponible (dejando padding)
          const maxWidth = Math.min(containerWidth - 48, 1400); // 48px de padding total
          const maxHeight = Math.min(containerHeight - 48, 1000);

          const imageAspectRatio = tempImg.width! / tempImg.height!;

          // Calcular dimensiones del canvas para mostrar la imagen lo más grande posible
          if (imageAspectRatio > maxWidth / maxHeight) {
            // La imagen es más ancha proporcionalmente
            canvasWidth = maxWidth;
            canvasHeight = canvasWidth / imageAspectRatio;
          } else {
            // La imagen es más alta proporcionalmente
            canvasHeight = maxHeight;
            canvasWidth = canvasHeight * imageAspectRatio;
          }

          // Asegurar dimensiones mínimas razonables
          canvasWidth = Math.max(400, canvasWidth);
          canvasHeight = Math.max(300, canvasHeight);
        }
      } catch (error) {
        console.error('Error obteniendo dimensiones de imagen:', error);
      }
    }

    // Inicializar canvas de Fabric.js con las dimensiones calculadas
    this.canvas = new Canvas(this.canvasElement.nativeElement, {
      width: canvasWidth,
      height: canvasHeight,
      backgroundColor: 'transparent', // Eliminar fondo gris
      enableRetinaScaling: false // Desactivar para mejor rendimiento
    });

    // Configurar event listeners pasivos después de la inicialización
    this.setupPassiveEventListeners();

    // Cargar imagen de fondo si existe
    if (this.imageUrl) {
      try {
        await this.loadBackgroundImage();
      } catch (error) {
        console.error('Error cargando imagen de fondo:', error);
        // Continuar sin imagen de fondo
      }
    }

    // Cargar posiciones existentes
    this.loadExistingPositions();

    // Configurar eventos del canvas
    this.setupCanvasEvents();

    this.isLoading.set(false);
  }

  private setupPassiveEventListeners(): void {
    // Configurar event listeners pasivos para evitar warnings de Chrome
    const canvasElement = this.canvasElement.nativeElement;

    // Configurar zoom con event listener pasivo
    const handleWheel = (e: WheelEvent) => {
      e.preventDefault();
      const delta = e.deltaY;
      let zoom = this.canvas.getZoom();
      zoom *= 0.999 ** delta;
      if (zoom > 20) zoom = 20;
      if (zoom < 0.01) zoom = 0.01;
      this.canvas.zoomToPoint(new Point(e.offsetX, e.offsetY), zoom);
    };

    // Añadir event listener pasivo para wheel
    canvasElement.addEventListener('wheel', handleWheel, { passive: false });

    // Guardar referencia para limpieza posterior
    (canvasElement as any)._customWheelHandler = handleWheel;
  }

  private async loadBackgroundImage(): Promise<void> {
    try {
      // Usar la sintaxis correcta de Fabric.js v6 con Promise
      const img = await FabricImage.fromURL(this.imageUrl, {}, {});

      if (img && img.getElement()) {
        // Las dimensiones originales ya se guardaron en initializeCanvas
        if (!this.originalImageSize) {
          this.originalImageSize = {
            width: img.width!,
            height: img.height!
          };
        }

        // Ajustar imagen para que llene completamente el canvas
        const canvasWidth = this.canvas.getWidth();
        const canvasHeight = this.canvas.getHeight();

        // Calcular escala para llenar el canvas completamente
        const scaleX = canvasWidth / img.width!;
        const scaleY = canvasHeight / img.height!;
        const scale = Math.max(scaleX, scaleY); // Usar max para llenar completamente

        // Centrar la imagen
        const scaledWidth = img.width! * scale;
        const scaledHeight = img.height! * scale;

        img.set({
          scaleX: scale,
          scaleY: scale,
          left: (canvasWidth - scaledWidth) / 2,
          top: (canvasHeight - scaledHeight) / 2,
          selectable: false,
          evented: false
        });

        this.backgroundImage = img;
        this.canvas.add(img);
        this.canvas.sendObjectToBack(img); // Usar el método correcto para enviar al fondo
        this.canvas.renderAll();
      } else {
        console.error('Error al cargar la imagen o imagen no válida');
        throw new Error('Error al cargar la imagen');
      }
    } catch (error) {
      console.error('Error loading background image:', error);
      throw error;
    }
  }

  private loadExistingPositions(): void {
    // Cargar posición del QR si existe
    if (this.qrPosition) {
      this.addQRElement(this.qrPosition);
    }

    // Cargar posición del nombre si existe
    if (this.namePosition) {
      this.addNameElement(this.namePosition);
    }
  }

  private setupCanvasEvents(): void {
    // Evento cuando se modifica un objeto
    this.canvas.on('object:modified', () => {
      this.emitPositionChanges();
    });

    // Evento cuando se mueve un objeto
    this.canvas.on('object:moving', () => {
      this.emitPositionChanges();
    });

    // Evento cuando se escala un objeto
    this.canvas.on('object:scaling', () => {
      this.emitPositionChanges();
    });
  }

  selectTool(tool: 'qr' | 'name'): void {
    this.selectedTool.set(tool);

    if (tool === 'qr' && !this.qrElement) {
      this.addQRElement();
    } else if (tool === 'name' && !this.nameElement) {
      this.addNameElement();
    }
  }

  private addQRElement(position?: ElementPosition): void {
    const qrRect = new Rect({
      left: position?.x || 100,
      top: position?.y || 100,
      width: position?.width || this.QR_SIZE,
      height: position?.height || this.QR_SIZE,
      fill: 'rgba(0, 123, 255, 0.3)',
      stroke: '#007bff',
      strokeWidth: 2,
      cornerColor: '#007bff',
      cornerSize: 8,
      transparentCorners: false
    });

    // Agregar texto indicativo
    const qrText = new FabricText('QR', {
      left: (position?.x || 100) + (position?.width || this.QR_SIZE) / 2,
      top: (position?.y || 100) + (position?.height || this.QR_SIZE) / 2,
      fontSize: 16,
      fill: '#007bff',
      fontWeight: 'bold',
      originX: 'center',
      originY: 'center',
      selectable: false,
      evented: false
    });

    this.qrElement = qrRect;
    this.canvas.add(qrRect);
    this.canvas.add(qrText);

    // Sincronizar movimiento del texto con el rectángulo
    qrRect.on('moving', () => {
      qrText.set({
        left: qrRect.left! + qrRect.width! / 2,
        top: qrRect.top! + qrRect.height! / 2
      });
    });

    qrRect.on('scaling', () => {
      qrText.set({
        left: qrRect.left! + (qrRect.width! * qrRect.scaleX!) / 2,
        top: qrRect.top! + (qrRect.height! * qrRect.scaleY!) / 2
      });
    });

    this.canvas.setActiveObject(qrRect);
    this.canvas.renderAll();
  }

  private addNameElement(position?: ElementPosition): void {
    const nameText = new FabricText('Nombre del Usuario', {
      left: position?.x || 200,
      top: position?.y || 200,
      width: position?.width || 200,
      fontSize: position?.fontSize || this.DEFAULT_FONT_SIZE,
      fontFamily: position?.fontFamily || this.DEFAULT_FONT_FAMILY,
      fill: 'rgba(40, 167, 69, 0.8)',
      backgroundColor: 'rgba(255, 255, 255, 0.8)',
      textAlign: 'center',
      cornerColor: '#28a745',
      cornerSize: 8,
      transparentCorners: false,
      padding: 10
    });

    this.nameElement = nameText;
    this.canvas.add(nameText);
    this.canvas.setActiveObject(nameText);
    this.canvas.renderAll();
  }

  removeQRElement(): void {
    if (this.qrElement) {
      this.canvas.remove(this.qrElement);
      this.qrElement = undefined;
      this.emitPositionChanges();
    }
  }

  removeNameElement(): void {
    if (this.nameElement) {
      this.canvas.remove(this.nameElement);
      this.nameElement = undefined;
      this.emitPositionChanges();
    }
  }

  changeFontSize(size: number): void {
    if (this.nameElement) {
      this.nameElement.set('fontSize', size);
      this.canvas.renderAll();
      this.emitPositionChanges();
    }
  }

  changeFontFamily(family: string): void {
    if (this.nameElement) {
      this.nameElement.set('fontFamily', family);
      this.canvas.renderAll();
      this.emitPositionChanges();
    }
  }

  private emitPositionChanges(): void {
    const qrPosition = this.qrElement ? {
      x: Math.round(this.qrElement.left || 0),
      y: Math.round(this.qrElement.top || 0),
      width: Math.round((this.qrElement.width || 0) * (this.qrElement.scaleX || 1)),
      height: Math.round((this.qrElement.height || 0) * (this.qrElement.scaleY || 1))
    } : null;

    const namePosition = this.nameElement ? {
      x: Math.round(this.nameElement.left || 0),
      y: Math.round(this.nameElement.top || 0),
      width: Math.round(this.nameElement.width || 0),
      height: Math.round(this.nameElement.height || 0),
      fontSize: this.nameElement.fontSize,
      fontFamily: this.nameElement.fontFamily,
      fontWeight: this.nameElement.fontWeight,
      color: this.nameElement.fill
    } : null;

    const templateStyles = {
      editor_canvas_size: {
        width: this.canvas ? this.canvas.getWidth() : 800,
        height: this.canvas ? this.canvas.getHeight() : 600
      },
      coords_origin: 'left-top'
    };

    this.positionsChanged.emit({ qrPosition, namePosition, templateStyles } as any);
  }

  savePositions(): void {
    // Para plantillas nuevas (sin ID), solo emitir los cambios sin guardar en backend
    if (!this.templateId || this.templateId === 0) {
      console.log('Plantilla nueva detectada, guardando posiciones temporalmente');
      this.emitPositionChanges();
      this.closed.emit();
      return;
    }

    this.isLoading.set(true);

    const qrPosition = this.qrElement ? {
      x: Math.round(this.qrElement.left || 0),
      y: Math.round(this.qrElement.top || 0),
      width: Math.round((this.qrElement.width || 0) * (this.qrElement.scaleX || 1)),
      height: Math.round((this.qrElement.height || 0) * (this.qrElement.scaleY || 1))
    } : null;

    const namePosition = this.nameElement ? {
      x: Math.round(this.nameElement.left || 0),
      y: Math.round(this.nameElement.top || 0),
      width: Math.round(this.nameElement.width || 0),
      height: Math.round(this.nameElement.height || 0),
      fontSize: this.nameElement.fontSize,
      fontFamily: this.nameElement.fontFamily,
      fontWeight: this.nameElement.fontWeight,
      color: this.nameElement.fill
    } : null;

    const backgroundImageSize = this.originalImageSize ? {
      width: this.originalImageSize.width,
      height: this.originalImageSize.height
    } : null;

    const positionsData = {
      qr_position: qrPosition,
      name_position: namePosition,
      background_image_size: backgroundImageSize,
      template_styles: {
        editor_canvas_size: {
          width: this.canvas.getWidth(),
          height: this.canvas.getHeight()
        },
        coords_origin: 'left-top' // Explicitly state origin
      }
    };

    this.templateService.updateTemplatePositions(this.templateId, positionsData).subscribe({
      next: (response) => {
        console.log('Posiciones guardadas exitosamente:', response);
        this.isLoading.set(false);
        this.closed.emit();
      },
      error: (error) => {
        console.error('Error al guardar posiciones:', error);
        this.isLoading.set(false);
        // Mantener el editor abierto en caso de error
      }
    });
  }

  cancelEdit(): void {
    this.closed.emit();
  }
}
