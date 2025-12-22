import PyPDF2
import re
import spacy
from sklearn.feature_extraction.text import TfidfVectorizer
import numpy as np
from transformers import T5ForConditionalGeneration, T5Tokenizer,pipeline
import json
import sys
from sklearn.cluster import KMeans
from sentence_transformers import SentenceTransformer
import os, logging
# Turn off tokenizer parallelism warning
os.environ["TOKENIZERS_PARALLELISM"] = "false"

# Silence the root logger (will suppress spaCy, PyPDF2, numpy, etc.)
logging.getLogger().setLevel(logging.ERROR)

# Silence HuggingFace Transformers
from transformers import logging as hf_logging
hf_logging.set_verbosity_error()


#Load QA pipeline for answer extraction
distilbert_qa = pipeline("question-answering", model="bert-large-uncased-whole-word-masking-finetuned-squad")

#load the fine-tuned model
model_name = "./t5-finetuned-question-generation"
tokenizer = T5Tokenizer.from_pretrained(model_name)
model = T5ForConditionalGeneration.from_pretrained(model_name)

def extract_text_from_pdf(pdf_path):
    """Extract text from a PDF file."""
    text=""
    with open(pdf_path, "rb") as file:
        pdf_reader = PyPDF2.PdfReader(file)
        for page in pdf_reader.pages:
            text += page.extract_text() + "\n"
    return text

def remove_generic_patterns(text):
    #remove page numbers
    text = re.sub(r'\n\d+\n', '\n', text)  # Remove page numbers
    text = re.sub(r'\n+\n', '\n', text)  # Remove extra newlines
    text = re.sub(r'\s+', ' ', text)  # Remove extra spaces
    text = re.sub(r'https?://\S+|www\.\S+','',text)  # Remove URLs
    return text.strip()

def remove_duplicate_sentences(text):
    sentences = text.split('.')
    unique_sentences = list(dict.fromkeys(sentences))
    return '. '.join(unique_sentences)

def remove_special_characters(text):
    text = re.sub(r'[^a-zA-Z0-9\s.,;:!?\'\"-]', '', text)  # Remove special characters
    return text

#spacy model for advanced text processing
nlp = spacy.load('en_core_web_md')

def split_into_sentences(text):
    doc = nlp(text)
    sentences = [sent.text.strip() for sent in doc.sents]
    return sentences

expander = pipeline(
    "text2text-generation",
    model="google/flan-t5-base",
    tokenizer="google/flan-t5-base",
    device=model.device
)

def make_descriptive_answer(question, context):
    res = distilbert_qa(question=question, context=context)
    answer_span = res["answer"]
    # now ask the generator to elaborate
    prompt = (
        f"Question: {question}\n"
        f"Answer: {answer_span}\n"
        "Explain why this is correct in two to three sentences."
    )
    out = expander(
        prompt,
        max_length=125,           # enough room for 2–3 sentences
        num_beams=4,
        early_stopping=True
    )[0]["generated_text"]
    return out
def main(pdf_path):
    
    try:
        # Question‑generation checkpoint
        qg_ckpt      = "valhalla/t5-small-e2e-qg"
        qg_tokenizer = T5Tokenizer.from_pretrained(qg_ckpt)
        qg_model     = T5ForConditionalGeneration.from_pretrained(qg_ckpt).to(model.device)

        # QA checkpoint
        qa_ckpt      = "mrm8488/t5-small-finetuned-squadv2"
        qa_tokenizer = T5Tokenizer.from_pretrained(qa_ckpt)
        qa_model     = T5ForConditionalGeneration.from_pretrained(qa_ckpt).to(model.device)

        summarizer = pipeline(
            "summarization",
            model="facebook/bart-large-cnn",
            tokenizer="facebook/bart-large-cnn")

        qg_pipe = pipeline(
            "text2text-generation",
            model="valhalla/t5-small-e2e-qg",
            tokenizer="valhalla/t5-small-e2e-qg",
            device=model.device,
        )
        

        # 4) Your existing PDF→text→sentences pipeline
        text     = extract_text_from_pdf(pdf_path)
        cleaned  = remove_generic_patterns(text)
        cleaned  = remove_duplicate_sentences(cleaned)
        cleaned  = remove_special_characters(cleaned)
        #sections = [cleaned[i:i+2000] for i in range(0, len(cleaned), 2000)]
        #summaries = [summarizer(chunk, max_length=200, min_length=50)[0]["summary_text"]
                    #for chunk in sections]

        # combine all summaries into one master summary
        #master_summary = " ".join(summaries)

        sentences = split_into_sentences(cleaned)

        N = len(sentences)
        k = max(1,N//12)
        num_questions = 12
        n_clusters = min(num_questions,N)

        #emb = SentenceTransformer("all-MiniLM-L6-v2").encode(sentences)
        #labels = KMeans(n_clusters=n_clusters, random_state=0).fit_predict(emb)
        #chunks = []
        #for c in range(n_clusters):
            #idxs = np.where(labels==c)[0]
            # pick the median sentence plus its neighbors
            #i = idxs[len(idxs)//2]
            #chunks.append(" ".join(sentences[max(0,i-1):i+2]))

        
         # 4) Rank by TF‑IDF and pick the top 5
        X        = TfidfVectorizer(stop_words="english").fit_transform(sentences)
        scores = X.sum(axis=1).A1
        top_idxs = scores.argsort()[::-1][:12]

        paragraphs = []
        for i in top_idxs:
            start = max(0,i-1)
            end = min(len(sentences),i+2)
            paragraphs.append(" ".join(sentences[start:end]))
        top_ctxs = paragraphs

        # 5) Batch-generate 2 candidates per context, then pick the best via QA confidence

        # a) Build prompts
        prompts = [f"generate question: {ctx}" for ctx in top_ctxs]

        # b) Do one pipeline() call for all contexts
        raw_outputs = qg_pipe(
            prompts,
            max_length=128,
            min_length=10,
            num_beams=7,
            num_return_sequences=2,
            early_stopping=True,
            batch_size=4,
        )
        if isinstance(raw_outputs[0], dict):
            grouped = [ raw_outputs[i*2:(i+1)*2] for i in range(len(prompts)) ]
        else:
            grouped = raw_outputs
        # c) For each context, choose the question with the highest distilbert_qa score
        selected_questions = []
        for idx, outputs in enumerate(grouped):
            best_q, best_score = None, -1.0
            # `outputs` is a list of 2 generation results (dicts), because num_return_sequences=2
            for gen in outputs:
                text = gen["generated_text"]
                # split on <sep> to handle valhalla/t5-small-e2e-qg style outputs
                for cand in text.split("<sep>")[:2]:
                    cand = cand.strip()
                    if not cand: continue
                    qa_res = distilbert_qa(question=cand, context=top_ctxs[idx])
                    if qa_res["score"] > best_score:
                        best_score, best_q = qa_res["score"], cand
            if not best_q:
                best_q = "What is the main idea of this paragraph?"
            selected_questions.append(best_q)

        # d) Now generate the descriptive answers for each selected question
        quiz = []
        for question, ctx in zip(selected_questions, top_ctxs):
            explanation = make_descriptive_answer(question, ctx)
            quiz.append({
                "question": question,
                "answer":   explanation
            })

        # e) Finally, print your full quiz JSON
        print(json.dumps({"quiz": quiz}))
        sys.exit(0)



    except FileNotFoundError as e:
        print(json.dumps({"error": f"File not found: {e}"}))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No PDF path provided"}))
        sys.exit(1)
    main(sys.argv[1])
    
