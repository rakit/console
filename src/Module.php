<?php

class ModuleArticle extends Module
{

    protected $name = "article";

    public function requirements($we)
    {
        $we->needTable(function($table) {
            $table->primary("id");
            $table->string("title");
            $table->string("slug")->unique();
            $table->string("content");
            $table->timestamps();
        });

        $we->wantPageList(function($page) {
            $page->setTitle("List Artikel");
            $page->showSearchField(["title", "content"]);
            $page->showPagination(5, 15);
            $page->showCreateButton();
            $page->showEditButton();
            $page->showDeleteButton();
        })
        ->forRoles(["admin", "developer"]);

        $we->wantFormCreate(function($form) {
            $form->setTitle("Buat Artikel Baru");

            $form->showTextField("title", "Judul")
                ->withRules("required");

            $form->showSelectOption("id_category", "Kategori")
                ->withOptionsFromTable("category", "id", "name");

            $form->showTextField("slug", "Slug")
                ->withRules("required|unique");

            $form->showRichTextarea("name", "Konten")
                ->withRules("required");
        })
        ->forRoles(["admin", "developer"]);

        $we->wantFormEdit(function($form) {
            $form->setTitle("Buat Artikel Baru");

            $form->showTextField("title", "Judul")
                ->withRules("required");

            $form->showSelectOption("id_category", "Kategori")
                ->withOptionsFromTable("category", "id", "name");

            $form->showTextField("slug", "Slug")
                ->withRules("required|unique");

            $form->showRichTextarea("name", "Konten")
                ->withRules("required");
        })
        ->forRoles(["admin", "developer"]);

        $we->wantFunctionDelete()->forRoles(["admin", "developer"]);
    }

}
