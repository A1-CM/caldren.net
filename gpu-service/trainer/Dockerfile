FROM nvcr.io/nvidia/rapidsai/base:26.08-cuda13-py3.14
WORKDIR /trainer
COPY train.py /trainer/train.py
ENTRYPOINT ["python", "/trainer/train.py"]
