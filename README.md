# PDF-to-Quiz Generator

This project takes PDF study material and automatically turns it into quizzes.

I originally built it as a class project because I wanted to create something students could use to generate practice material without paying for another study tool. The project was later developed into a small research project and presented as conference work.

## What it does

The basic pipeline is:

PDF → text extraction → text cleaning → sentence ranking → question generation → quiz

The system extracts text from an uploaded PDF, cleans and processes the text, identifies useful sentences, and uses pretrained T5 transformer models to generate questions and answers.

Generated quizzes can then be stored for individual users through the accompanying database-backed application.

## Main workflow

1. Extract text from the uploaded PDF.
2. Clean formatting and unwanted text.
3. Split the text into sentences.
4. Rank potentially useful sentences using TF-IDF-based scoring.
5. Send selected text to pretrained T5 models for question and answer generation.
6. Store the resulting quiz so it can be used later.

## Technologies

- Python
- PyPDF2
- spaCy
- scikit-learn
- TF-IDF
- NumPy
- Hugging Face Transformers
- T5
- MySQL

## Development

One of the more difficult parts of the project was handling PDFs containing large numbers of mathematical symbols, unusual formatting, and other content that does not extract cleanly.

I also experimented with training a model specifically for the project. In practice, training was computationally expensive and the results were not better than the pretrained models I tested, so the final system uses pretrained T5 models instead.

Another part of the development process involved adjusting how candidate text was weighted and selected before being passed to the generation model. The quality of the selected source text had a noticeable effect on the generated questions.

## Limitations

This is a research and learning project rather than a production system.

Some current limitations are:

- Question and answer quality can be inconsistent.
- Generation can be relatively slow.
- Performance depends heavily on the structure and quality of the source PDF.
- PDFs containing mathematical notation or unusual formatting are more difficult to process reliably.
- No formal benchmark or large-scale evaluation was performed.

The project is also not currently hosted online.

## Database

The application uses a database to store information such as users, quizzes, generated questions, and quiz results.

Database credentials and local configuration should be supplied separately and are not included in the repository.

## Running the project

The repository contains the source code required to run the project locally.

Exact setup depends on the local Python and MySQL configuration. Installation and execution instructions will be added as the project is cleaned up further.

## Research

This project began as a class project and was later developed into research work that was presented at a conference, CSCI'25.


## Future work

I may return to this project in the future. The main areas I would improve are:

- question quality,
- answer quality,
- processing speed,
- handling of mathematical and unusually formatted PDFs,
- and more systematic evaluation of generated quizzes.
