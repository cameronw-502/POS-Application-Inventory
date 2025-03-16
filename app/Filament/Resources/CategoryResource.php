<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->relationship('parent', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select a parent category')
                                    ->helperText('Leave empty for top-level category (department)')
                                    ->columnSpanFull(),
                                
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                                        $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                                
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Category::class, 'slug', ignoreRecord: true)
                                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                                    ->dehydrated(),
                                
                                Forms\Components\ColorPicker::make('color')
                                    ->label('Category Color')
                                    ->default('#3490dc'),

                                Forms\Components\TextInput::make('display_order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers appear first'),
                                    
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                
                                Forms\Components\Textarea::make('description')
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('path')
                    ->label('Category Path')
                    ->searchable(['name'])
                    ->sortable(['name']),
                
                Tables\Columns\ColorColumn::make('color')
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('display_order')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->sortable()
                    ->label('Products'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('parent_id')
                    ->label('Category Level')
                    ->placeholder('All Categories')
                    ->trueLabel('Top Level Only (Departments)')
                    ->falseLabel('Sub-Categories Only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('parent_id'),
                        false: fn (Builder $query) => $query->whereNotNull('parent_id'),
                    ),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Category $record) => $record->products()->count() > 0 || $record->children()->count() > 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            // Only delete categories with no products or children
                            $records->each(function (Category $record) {
                                if ($record->products()->count() === 0 && $record->children()->count() === 0) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
            'view' => Pages\ViewCategory::route('/{record}'),
        ];
    }
}
